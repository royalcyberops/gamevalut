<?php
// includes/cart.php
// Session-based shopping cart for GameVault Shop.
// Cart shape: $_SESSION['cart'] = [game_id => qty]. Nothing touches the
// database here — prices are always re-read from games at display/checkout
// time, so a tampered session can never change what the user pays.

require_once __DIR__ . '/session.php'; // guarantees the session is started

function cart_get()
{
    $cart = $_SESSION['cart'] ?? [];
    return is_array($cart) ? $cart : [];
}

function cart_count()
{
    return array_sum(cart_get());
}

function cart_add($game_id, $qty = 1)
{
    $game_id = (int) $game_id;
    $qty     = (int) $qty;
    if ($game_id <= 0) {
        return;
    }
    // Per-game quantity rule (mirrors checkout validation): 1-5.
    $qty = max(1, min(5, $qty));
    $cart = cart_get();
    $cart[$game_id] = min(5, ($cart[$game_id] ?? 0) + $qty);
    $_SESSION['cart'] = $cart;
}

function cart_set_qty($game_id, $qty)
{
    $game_id = (int) $game_id;
    $qty     = (int) $qty;
    $cart = cart_get();
    if (!isset($cart[$game_id])) {
        return;
    }
    if ($qty <= 0) {
        unset($cart[$game_id]); // qty 0 = remove the line
    } else {
        $cart[$game_id] = max(1, min(5, $qty));
    }
    $_SESSION['cart'] = $cart;
}

function cart_remove($game_id)
{
    $cart = cart_get();
    unset($cart[(int) $game_id]);
    $_SESSION['cart'] = $cart;
}

function cart_clear()
{
    $_SESSION['cart'] = [];
}

function cart_prune($valid_ids)
{
    // Drop session ids that no longer exist in the catalogue (ghost lines),
    // so nav count, rendered rows, and totals always agree.
    if (!is_array($valid_ids)) {
        return;
    }
    $valid = [];
    foreach ($valid_ids as $vid) {
        if (is_numeric($vid)) {
            $valid[(int) $vid] = true;
        }
    }
    $cart = cart_get();
    $changed = false;
    foreach ($cart as $gid => $qty) {
        if (!isset($valid[(int) $gid])) {
            unset($cart[(int) $gid]);
            $changed = true;
        }
    }
    if ($changed) {
        $_SESSION['cart'] = $cart;
    }
}
