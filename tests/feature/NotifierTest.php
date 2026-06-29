<?php

declare(strict_types=1);

use App\Services\Notifier;
use Core\Database;

group('Notifier (feature · requiere MySQL)');

it('notifica, cuenta no leídas, marca leída', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }

    $uid = 987654; // usuario de prueba
    Database::run('DELETE FROM notifications WHERE user_id = ?', [$uid]);

    Notifier::notify($uid, 'Hola', 'cuerpo', '/admin');
    Notifier::notify($uid, 'Chau');

    assertEquals(2, Notifier::unreadCount($uid));
    assertCount(2, Notifier::forUser($uid));

    $list = Notifier::forUser($uid, true);
    Notifier::markRead((int) $list[0]['id'], $uid);
    assertEquals(1, Notifier::unreadCount($uid));

    Notifier::markAllRead($uid);
    assertEquals(0, Notifier::unreadCount($uid));

    // limpiar
    Database::run('DELETE FROM notifications WHERE user_id = ?', [$uid]);
});

it('markRead no afecta a otro usuario', function () {
    try {
        Database::connection();
    } catch (\Throwable) {
        return 'skip';
    }
    $a = 987655;
    $b = 987656;
    Database::run('DELETE FROM notifications WHERE user_id IN (?, ?)', [$a, $b]);

    Notifier::notify($a, 'para A');
    $list = Notifier::forUser($a, true);
    // intentar marcar como B no debe tocarla
    Notifier::markRead((int) $list[0]['id'], $b);
    assertEquals(1, Notifier::unreadCount($a));

    Database::run('DELETE FROM notifications WHERE user_id IN (?, ?)', [$a, $b]);
});
