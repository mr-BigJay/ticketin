<?php

function ticket_status_normalize(string $status): string
{
    return $status === 'progress' ? 'pending' : $status;
}

function ticket_status_state_meta(string $status): ?array
{
    $status = ticket_status_normalize($status);

    $map = [
        'open' => [
            'class' => 'ticket-badge--open',
            'icon' => '🔵',
            'label' => 'باز',
        ],
        'closed' => [
            'class' => 'ticket-badge--closed',
            'icon' => '⚫',
            'label' => 'بسته',
        ],
        'pending' => [
            'class' => 'ticket-badge--pending',
            'icon' => '⏳',
            'label' => 'درحال بررسی',
        ],
    ];

    return $map[$status] ?? null;
}

function ticket_status_reply_meta(
    string $lastReplyBy,
    string $portal = 'user'
): ?array
{
    if($lastReplyBy === ''){
        return null;
    }

    if($portal === 'admin'){
        if($lastReplyBy === 'user_reply'){
            return [
                'class' => 'ticket-badge--reply-user',
                'icon' => '👤',
                'label' => 'پاسخ کاربر',
            ];
        }

        if($lastReplyBy === 'admin_reply'){
            return [
                'class' => 'ticket-badge--reply-you',
                'icon' => '✏️',
                'label' => 'پاسخ شما',
            ];
        }
    } else {
        if($lastReplyBy === 'admin_reply'){
            return [
                'class' => 'ticket-badge--reply-support',
                'icon' => '🛡️',
                'label' => 'پاسخ پشتیبان',
            ];
        }

        if($lastReplyBy === 'user_reply'){
            return [
                'class' => 'ticket-badge--reply-you',
                'icon' => '✏️',
                'label' => 'پاسخ شما',
            ];
        }
    }

    return null;
}

function ticket_status_closed_by_meta(
    array $ticket,
    string $portal = 'user'
): ?array
{
    if(ticket_status_normalize((string)($ticket['status'] ?? '')) !== 'closed'){
        return null;
    }

    $closedBy = (string)($ticket['closed_by'] ?? '');

    if($closedBy === 'user'){
        return [
            'class' => 'ticket-badge--closed-by-user',
            'icon' => '✓',
            'icon_class' => 'ticket-badge__icon--you',
            'label' => $portal === 'admin' ? 'توسط کاربر' : 'توسط شما',
        ];
    }

    if($closedBy === 'admin'){
        return [
            'class' => 'ticket-badge--closed-by-admin',
            'icon' => '🛡️',
            'icon_class' => 'ticket-badge__icon--support',
            'label' => $portal === 'admin' ? 'توسط شما' : 'توسط پشتیبان',
        ];
    }

    return null;
}

function ticket_status_print_styles(): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

    echo <<<'CSS'
<style>
.ticket-statuses{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
}
.ticket-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    line-height:1.2;
    border:1px solid transparent;
    white-space:nowrap;
}
.ticket-badge__icon{
    font-size:11px;
    line-height:1;
}
.ticket-badge__text{
    line-height:1.2;
}
.ticket-badge--open{
    background:#eff6ff;
    color:#0284c7;
    border-color:#bfdbfe;
}
.ticket-badge--closed{
    background:#f1f5f9;
    color:#64748b;
    border-color:#e2e8f0;
}
.ticket-badge--pending{
    background:#fffbeb;
    color:#d97706;
    border-color:#fde68a;
}
.ticket-badge--reply-user{
    background:#f5f3ff;
    color:#7c3aed;
    border-color:#ddd6fe;
}
.ticket-badge--reply-support{
    background:#ecfeff;
    color:#0891b2;
    border-color:#a5f3fc;
}
.ticket-badge--reply-you{
    background:#ecfdf5;
    color:#059669;
    border-color:#a7f3d0;
}
.ticket-badge--closed-by-user,
.ticket-badge--closed-by-admin{
    background:#374151;
    color:#f9fafb;
    border-color:#4b5563;
}
.ticket-badge__icon--you{
    color:#059669;
}
.ticket-badge__icon--support{
    color:#0891b2;
}
</style>
CSS;
}

function ticket_status_render_badge(?array $meta): void
{
    if($meta === null){
        return;
    }

    echo '<span class="ticket-badge ' . htmlspecialchars($meta['class'], ENT_QUOTES, 'UTF-8') . '">';

    $iconClass = 'ticket-badge__icon';

    if(!empty($meta['icon_class'])){
        $iconClass .= ' ' . $meta['icon_class'];
    }

    echo '<span class="' . htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true">' . $meta['icon'] . '</span>';
    echo '<span class="ticket-badge__text">' . htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') . '</span>';
    echo '</span>';
}

function ticket_status_render_ticket_badges(
    array $ticket,
    string $portal = 'user'
): void
{
    global $pdo;

    if(isset($pdo) && $pdo instanceof PDO){
        require_once __DIR__ . '/ticket_helpers.php';
        ticket_ensure_schema($pdo);
    }

    ticket_status_print_styles();

    $stateMeta = ticket_status_state_meta((string)($ticket['status'] ?? ''));
    $closedByMeta = ticket_status_closed_by_meta($ticket, $portal);
    $replyMeta = ticket_status_reply_meta(
        (string)($ticket['last_reply_by'] ?? ''),
        $portal
    );

    if($stateMeta === null && $closedByMeta === null && $replyMeta === null){
        return;
    }

    echo '<div class="ticket-statuses">';
    ticket_status_render_badge($stateMeta);
    ticket_status_render_badge($closedByMeta);
    ticket_status_render_badge($replyMeta);
    echo '</div>';
}
