<?php

require_once __DIR__ . '/jalali.php';

function pagination_allowed_limits(): array
{
    return [20, 50, 100];
}

function pagination_parse_request(): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['per_page'] ?? 20);

    if(!in_array($limit, pagination_allowed_limits(), true)){
        $limit = 20;
    }

    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => ($page - 1) * $limit,
    ];
}

function pagination_total_pages(int $total, int $limit): int
{
    return max(1, (int)ceil($total / $limit));
}

function pagination_clamp_page(int $page, int $totalPages): int
{
    return min(max(1, $page), $totalPages);
}

function pagination_range_text(int $page, int $limit, int $total): string
{
    if($total <= 0){
        return toPersianNumbers('۰ مورد');
    }

    $start = (($page - 1) * $limit) + 1;
    $end = min($page * $limit, $total);

    return toPersianNumbers(
        $start . '-' . $end . ' از ' . $total . ' مورد'
    );
}

function pagination_build_url(array $params, string $basePath = ''): string
{
    $filtered = [];

    foreach($params as $key => $value){
        if($value === '' || $value === null){
            continue;
        }

        $filtered[$key] = $value;
    }

    if(!$filtered){
        return $basePath !== '' ? $basePath : '?';
    }

    $query = http_build_query($filtered);

    if($basePath === ''){
        return '?' . $query;
    }

    $separator = strpos($basePath, '?') !== false ? '&' : '?';

    return $basePath . $separator . $query;
}

function pagination_print_styles(): void
{
    static $printed = false;

    if($printed){
        return;
    }

    $printed = true;

    echo <<<'CSS'
<style>
.list-pagination-bar{
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:6px;
    margin-top:12px;
    padding:6px 10px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:12px;
}
.list-pagination-per-page{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:5px;
    color:#64748b;
    font-size:11px;
    font-weight:700;
    flex-wrap:wrap;
}
.list-pagination-per-page select{
    min-width:52px;
    height:28px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    background:#fff;
    color:#0f172a;
    padding:0 6px;
    font-family:'Vazirmatn',sans-serif;
    font-size:11px;
    font-weight:800;
    cursor:pointer;
}
.list-pagination-nav{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    flex-wrap:wrap;
}
.list-pagination-buttons{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:5px;
}
.list-pagination-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:28px;
    height:28px;
    padding:0 6px;
    border-radius:8px;
    border:1px solid #e2e8f0;
    background:#fff;
    color:#334155;
    text-decoration:none;
    font-size:12px;
    font-weight:800;
    box-shadow:0 1px 4px rgba(15,23,42,.04);
    transition:.2s;
}
.list-pagination-btn:hover:not(.is-disabled){
    background:#f1f5f9;
}
.list-pagination-btn.is-current{
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:#fff;
    border-color:transparent;
    box-shadow:0 6px 16px rgba(2,132,199,.2);
}
.list-pagination-btn.is-disabled{
    opacity:.45;
    pointer-events:none;
    cursor:default;
}
.list-pagination-range{
    color:#64748b;
    font-size:11px;
    font-weight:700;
    white-space:nowrap;
    text-align:center;
}
</style>
CSS;
}

function pagination_render_bar(
    int $page,
    int $limit,
    int $total,
    int $totalPages,
    array $queryParams = [],
    string $basePath = ''
): void
{
    if($total <= 0){
        return;
    }

    $page = pagination_clamp_page($page, $totalPages);

    pagination_print_styles();

    $prevPage = max(1, $page - 1);
    $nextPage = min($totalPages, $page + 1);

    $baseParams = array_merge($queryParams, [
        'per_page' => $limit,
    ]);

    $prevUrl = pagination_build_url(
        array_merge($baseParams, ['page' => $prevPage]),
        $basePath
    );

    $nextUrl = pagination_build_url(
        array_merge($baseParams, ['page' => $nextPage]),
        $basePath
    );

    $rangeText = pagination_range_text($page, $limit, $total);

    echo '<div class="list-pagination-bar">';

    echo '<div class="list-pagination-per-page">';
    echo '<span>نمایش</span>';
    echo '<select class="list-pagination-limit" aria-label="تعداد نمایش در هر صفحه" data-base-path="' . htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') . '" data-query="' . htmlspecialchars(json_encode($queryParams, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '">';

    foreach(pagination_allowed_limits() as $allowedLimit){
        $selected = $allowedLimit === $limit ? ' selected' : '';
        echo '<option value="' . $allowedLimit . '"' . $selected . '>' . toPersianNumbers((string)$allowedLimit) . '</option>';
    }

    echo '</select>';
    echo '<span>مورد در هر صفحه</span>';
    echo '</div>';

    echo '<div class="list-pagination-nav">';
    echo '<div class="list-pagination-buttons">';

    $prevClass = 'list-pagination-btn' . ($page <= 1 ? ' is-disabled' : '');
    $nextClass = 'list-pagination-btn' . ($page >= $totalPages ? ' is-disabled' : '');

    echo '<a href="' . htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8') . '" class="' . $prevClass . '" aria-label="صفحه قبل">‹</a>';
    echo '<span class="list-pagination-btn is-current" aria-current="page">' . toPersianNumbers((string)$page) . '</span>';
    echo '<a href="' . htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') . '" class="' . $nextClass . '" aria-label="صفحه بعد">›</a>';

    echo '</div>';
    echo '<span class="list-pagination-range">' . htmlspecialchars($rangeText, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '</div>';

    echo '</div>';

    static $scriptPrinted = false;

    if(!$scriptPrinted){
        $scriptPrinted = true;

        echo <<<'JS'
<script>
document.addEventListener('change', function(event){
    const select = event.target.closest('.list-pagination-limit');

    if(!select){
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const extra = {};

    try{
        Object.assign(extra, JSON.parse(select.dataset.query || '{}'));
    }catch(error){
    }

    Object.keys(extra).forEach(function(key){
        if(extra[key] !== '' && extra[key] !== null && extra[key] !== undefined){
            params.set(key, extra[key]);
        }
    });

    params.set('per_page', select.value);
    params.set('page', '1');

    const basePath = select.dataset.basePath || window.location.pathname;
    const query = params.toString();
    window.location.href = query ? basePath + '?' + query : basePath;
});
</script>
JS;
    }
}
