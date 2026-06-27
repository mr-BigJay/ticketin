<?php

function category_ensure_schema(PDO $pdo): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

    try{
        $pdo->exec("
            ALTER TABLE categories
            ADD COLUMN parent_id INT NULL
        ");
    }catch(PDOException $e){
    }

    try{
        $pdo->exec("
            ALTER TABLE categories
            ADD INDEX idx_categories_parent (parent_id)
        ");
    }catch(PDOException $e){
    }
}

function category_parent_id_value($parentId): ?int
{
    if($parentId === null || $parentId === ''){
        return null;
    }

    $parentId = (int)$parentId;

    return $parentId > 0 ? $parentId : null;
}

function category_matches_parent(array $item, $parentId): bool
{
    $itemParent = $item['parent_id'] ?? null;

    if($itemParent === '' || $itemParent === '0'){
        $itemParent = null;
    }

    if($itemParent !== null){
        $itemParent = (int)$itemParent;
    }

    if($parentId === null || $parentId === '' || $parentId === 0){
        return $itemParent === null;
    }

    return $itemParent === (int)$parentId;
}

function category_get_roots(array $categories): array
{
    return array_values(array_filter(
        $categories,
        static function($category){
            return category_matches_parent($category, null);
        }
    ));
}

function category_has_children(PDO $pdo, int $categoryId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM categories
        WHERE parent_id=?
    ");

    $stmt->execute([$categoryId]);

    return (int)$stmt->fetchColumn() > 0;
}

function category_validate_parent(
    PDO $pdo,
    ?int $parentId,
    ?int $editId = null
): ?string
{
    if($parentId === null){
        return null;
    }

    if($editId && $parentId === $editId){
        return 'دسته نمی‌تواند والد خودش باشد';
    }

    $stmt = $pdo->prepare("
        SELECT id, parent_id
        FROM categories
        WHERE id=?
        LIMIT 1
    ");

    $stmt->execute([$parentId]);

    if(!$stmt->fetch()){
        return 'دسته اصلی انتخاب‌شده معتبر نیست';
    }

    if(!$editId){
        return null;
    }

    $walker = $parentId;

    while($walker){

        if($walker === $editId){
            return 'انتخاب این زیرمجموعه مجاز نیست';
        }

        $next = $pdo->prepare("
            SELECT parent_id
            FROM categories
            WHERE id=?
            LIMIT 1
        ");

        $next->execute([$walker]);
        $row = $next->fetch(PDO::FETCH_ASSOC);

        if(empty($row['parent_id'])){
            break;
        }

        $walker = (int)$row['parent_id'];
    }

    return null;
}

function category_render_tree(
    array $items,
    $parent = null,
    int $depth = 0
): void
{
    foreach($items as $item){

        if(!category_matches_parent($item, $parent)){
            continue;
        }

        $isRoot = $depth === 0;

        ?>

        <div class="category-tree-item<?= $isRoot ? ' is-root' : '' ?>">

            <div class="category-tree-node" style="--tree-depth:<?= (int)$depth ?>">

                <div class="category-tree-label">

                    <span class="category-tree-icon" aria-hidden="true">
                    <?= $isRoot ? '📁' : '📂' ?>
                    </span>

                    <span class="category-tree-name">
                    <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <?php if(!$isRoot): ?>
                    <span class="category-tree-badge">زیرمجموعه</span>
                    <?php else: ?>
                    <span class="category-tree-badge is-main">اصلی</span>
                    <?php endif; ?>

                </div>

                <div class="category-tree-actions">

                    <a
                    href="?edit=<?= (int)$item['id'] ?>"
                    class="category-tree-btn edit">

                    ویرایش

                    </a>

                    <a
                    href="?delete=<?= (int)$item['id'] ?>"
                    class="category-tree-btn delete"
                    onclick="return confirm('حذف شود؟')">

                    حذف

                    </a>

                </div>

            </div>

            <?php if(category_get_children_count($items, (int)$item['id']) > 0): ?>

            <div class="category-tree-children">

                <?php category_render_tree($items, (int)$item['id'], $depth + 1); ?>

            </div>

            <?php endif; ?>

        </div>

        <?php

    }
}

function category_get_children_count(array $items, int $parentId): int
{
    $count = 0;

    foreach($items as $item){

        if(category_matches_parent($item, $parentId)){
            $count++;
        }

    }

    return $count;
}

function category_flat_options(array $categories): array
{
    $options = [];

    $append = static function($parentId, $prefix = '') use (&$append, $categories, &$options){

        foreach($categories as $category){

            if(!category_matches_parent($category, $parentId)){
                continue;
            }

            $label = $prefix . $category['name'];
            $options[] = [
                'id' => (int)$category['id'],
                'name' => $category['name'],
                'label' => $label,
                'parent_id' => $category['parent_id'] ?? null,
                'is_leaf' => category_get_children_count($categories, (int)$category['id']) === 0,
            ];

            $append(
                (int)$category['id'],
                $label . ' / '
            );

        }

    };

    $append(null);

    return $options;
}
