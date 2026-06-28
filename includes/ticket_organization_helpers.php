<?php

function ticket_org_user_locations(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT
            uor.center_id,
            uor.node_id,
            center.name AS center_name,
            center.center_category,
            center.sort_order AS center_sort,
            child.name AS node_name,
            child.type AS node_type
        FROM user_organization_rel uor
        INNER JOIN organization_nodes center
            ON uor.center_id = center.id
        INNER JOIN organization_nodes child
            ON uor.node_id = child.id
        WHERE uor.user_id = ?
        ORDER BY center.sort_order ASC, center.name ASC, child.name ASC
    ");

    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ticket_org_user_centers(array $locations): array
{
    $centers = [];

    foreach($locations as $location){
        $centerId = (int)$location['center_id'];

        if(isset($centers[$centerId])){
            continue;
        }

        $centers[$centerId] = [
            'id' => $centerId,
            'name' => $location['center_name'],
            'center_category' => $location['center_category'] ?? '',
            'sort_order' => (int)($location['center_sort'] ?? 0),
        ];
    }

    $centers = array_values($centers);

    usort($centers, static function(array $a, array $b): int{
        $sort = $a['sort_order'] <=> $b['sort_order'];

        if($sort !== 0){
            return $sort;
        }

        return strcmp($a['name'], $b['name']);
    });

    return $centers;
}

function ticket_org_is_staff_center(?string $category): bool
{
    return ($category ?? '') === 'administrative';
}

function ticket_org_location_label(array $location): string
{
    $centerName = trim((string)($location['center_name'] ?? ''));
    $nodeName = trim((string)(
        $location['node_name']
        ?? $location['child_name']
        ?? ''
    ));
    $nodeType = (string)(
        $location['node_type']
        ?? $location['child_type']
        ?? ''
    );

    if($nodeType === 'health_house'){
        return $centerName . ' - خانه بهداشت ' . $nodeName;
    }

    return $centerName . ' - واحد ' . $nodeName;
}

function ticket_org_location_type_label(array $location): string
{
    $nodeType = (string)(
        $location['node_type']
        ?? $location['child_type']
        ?? ''
    );
    $centerCategory = (string)($location['center_category'] ?? '');

    if($nodeType === 'health_house'){
        return 'خانه بهداشت';
    }

    if(ticket_org_is_staff_center($centerCategory)){
        return 'واحد ستادی';
    }

    return 'واحد مستقر در مرکز';
}

function ticket_org_find_location(array $locations, int $nodeId): ?array
{
    foreach($locations as $location){
        if((int)($location['node_id'] ?? 0) === $nodeId){
            return $location;
        }
    }

    return null;
}

function ticket_org_validate_selection(
    PDO $pdo,
    int $userId,
    int $centerId,
    string $subType,
    int $subId
): ?string
{
    if(!in_array($subType, ['unit', 'health_house'], true)){
        return 'نوع زیرمجموعه نامعتبر است';
    }

    foreach(ticket_org_user_locations($pdo, $userId) as $location){
        if(
            (int)$location['center_id'] !== $centerId
            ||
            (int)$location['node_id'] !== $subId
            ||
            ($location['node_type'] ?? '') !== $subType
        ){
            continue;
        }

        if(
            ticket_org_is_staff_center($location['center_category'] ?? null)
            &&
            $subType !== 'unit'
        ){
            return 'مراکز ستادی فقط واحد مستقر دارند';
        }

        return null;
    }

    return 'فقط می‌توانید برای محل‌های خدمت خودتان تیکت ثبت کنید';
}
