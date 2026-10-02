<?php
/**
 * The delegated event dispatcher, as one line a page can include.
 *
 * Pages that use includes/header.php or includes/sidebar.php get this
 * for free. The ones that build their own <head> - network_topology.php,
 * billing/*, hotspot/admin/index.php, hotspot/admin/blacklist.php - have
 * to include it themselves, and until they do, any data-action in their
 * markup is a button that silently does nothing.
 *
 * It is a file rather than a copied <script> tag so that there is one
 * place to change when the path or the loading strategy changes.
 */

declare(strict_types=1);

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/html.php';
?>
<script src="<?= e(app_base_path()) ?>assets/js/actions.js" defer></script>
