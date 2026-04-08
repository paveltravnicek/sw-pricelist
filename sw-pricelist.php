<?php
/**
 * Plugin Name: Jednoduché ceníky
 * Description: Jednoduchý tvůrce více ceníků se shortcode pro výpis kategorií a položek.
 * Version: 1.0
 * Author: Smart Websites
 * Author URI: https://smart-websites.cz
 * Text Domain: sw-jednoduche-ceniky
 */

if (!defined('ABSPATH')) {
    exit;
}

class SW_Jednoduche_Ceniky {
    const OPTION_KEY = 'sw_jednoduche_ceniky_data';
    const NONCE_KEY  = 'sw_jednoduche_ceniky_nonce';
    const SLUG       = 'sw-jednoduche-ceniky';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_post_sw_jednoduche_ceniky_save', [$this, 'handle_save']);
        add_shortcode('sw_cenik', [$this, 'shortcode']);
        add_shortcode('levitas_cenik', [$this, 'shortcode']); // legacy alias
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
    }

    public function add_settings_link($links) {
        array_unshift(
            $links,
            '<a href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">' . esc_html__('Nastavení', 'sw-jednoduche-ceniky') . '</a>'
        );
        return $links;
    }

    public function admin_menu() {
        add_menu_page(
            __('Jednoduché ceníky', 'sw-jednoduche-ceniky'),
            __('Jednoduché ceníky', 'sw-jednoduche-ceniky'),
            'manage_options',
            self::SLUG,
            [$this, 'render_admin_page'],
            'dashicons-list-view',
            10.6
        );
    }

    public function admin_assets($hook) {
        if ($hook !== 'toplevel_page_' . self::SLUG) {
            return;
        }

        $css_path = plugin_dir_path(__FILE__) . 'assets/admin.css';
        $js_path  = plugin_dir_path(__FILE__) . 'assets/admin.js';

        wp_enqueue_style(
            'sw-jednoduche-ceniky-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            [],
            file_exists($css_path) ? (string) filemtime($css_path) : '1.1.0'
        );

        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_script(
            'sw-jednoduche-ceniky-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            file_exists($js_path) ? (string) filemtime($js_path) : '1.1.0',
            true
        );
    }

    private function get_plugin_version() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $data = get_plugin_data(__FILE__, false, false);
        return isset($data['Version']) ? (string) $data['Version'] : '1.1.0';
    }

    private function new_id($prefix) {
        return $prefix . '_' . wp_generate_uuid4();
    }

    private function get_data() {
        $data = get_option(self::OPTION_KEY, []);
        if (!is_array($data)) {
            $data = [];
        }
        return $this->normalize_lists($data);
    }

    private function normalize_lists($lists) {
        $out = [];
        foreach ($lists as $list) {
            if (!is_array($list)) {
                continue;
            }

            $list_id    = isset($list['id']) ? sanitize_text_field($list['id']) : $this->new_id('list');
            $title      = isset($list['title']) ? sanitize_text_field($list['title']) : '';
            $key        = isset($list['key']) ? sanitize_title($list['key']) : sanitize_title($title);
            $currency   = isset($list['currency']) ? sanitize_text_field($list['currency']) : 'Kč';
            $from_label = isset($list['from_label']) ? sanitize_text_field($list['from_label']) : '';
            $order      = isset($list['order']) ? intval($list['order']) : 0;

            if ($key === '') {
                $key = '1';
            }

            $categories = [];
            $raw_categories = isset($list['categories']) && is_array($list['categories']) ? $list['categories'] : [];
            foreach ($raw_categories as $cat) {
                if (!is_array($cat)) {
                    continue;
                }
                $cat_id = isset($cat['id']) ? sanitize_text_field($cat['id']) : $this->new_id('cat');
                $cat_title = isset($cat['title']) ? sanitize_text_field($cat['title']) : '';
                $cat_order = isset($cat['order']) ? intval($cat['order']) : 0;

                $items_out = [];
                $items = isset($cat['items']) && is_array($cat['items']) ? $cat['items'] : [];
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $item_id = isset($item['id']) ? sanitize_text_field($item['id']) : $this->new_id('item');
                    $items_out[] = [
                        'id'        => $item_id,
                        'name'      => isset($item['name']) ? sanitize_text_field($item['name']) : '',
                        'note'      => isset($item['note']) ? sanitize_text_field($item['note']) : '',
                        'price'     => isset($item['price']) ? sanitize_text_field($item['price']) : '',
                        'show_from' => !empty($item['show_from']) ? 1 : 0,
                        'order'     => isset($item['order']) ? intval($item['order']) : 0,
                    ];
                }
                usort($items_out, static function ($a, $b) {
                    return $a['order'] <=> $b['order'];
                });

                $categories[] = [
                    'id'    => $cat_id,
                    'title' => $cat_title,
                    'order' => $cat_order,
                    'items' => $items_out,
                ];
            }
            usort($categories, static function ($a, $b) {
                return $a['order'] <=> $b['order'];
            });

            $out[] = [
                'id'         => $list_id,
                'title'      => $title,
                'key'        => $key,
                'currency'   => $currency,
                'from_label' => $from_label,
                'order'      => $order,
                'categories' => $categories,
            ];
        }

        usort($out, static function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        return $out;
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $lists = $this->get_data();
        ?>
        <div class="wrap swjc-wrap">
            <div class="swjc-hero">
                <div class="swjc-hero__content">
                    <span class="swjc-badge"><?php echo esc_html__('Smart Websites', 'sw-jednoduche-ceniky'); ?></span>
                    <h1><?php echo esc_html__('Jednoduché ceníky', 'sw-jednoduche-ceniky'); ?></h1>
                    <p><?php echo esc_html__('Vytvářejte více samostatných ceníků s vlastní měnou, textem před cenou a výpisem přes shortcode.', 'sw-jednoduche-ceniky'); ?></p>
                </div>
                <div class="swjc-hero__meta">
                    <div class="swjc-version-card">
                        <strong><?php echo esc_html($this->get_plugin_version()); ?></strong>
                        <span><?php echo esc_html__('Verze pluginu', 'sw-jednoduche-ceniky'); ?></span>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['updated']) && $_GET['updated'] === '1') : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Ceníky byly uloženy.', 'sw-jednoduche-ceniky'); ?></p></div>
            <?php endif; ?>

            <div class="notice notice-info"><p><?php echo wp_kses_post(__('Použijte shortcode <code>[sw_cenik]</code> pro první ceník nebo <code>[sw_cenik key="1"]</code> či jiný klíč pro konkrétní ceník.', 'sw-jednoduche-ceniky')); ?></p></div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="swjc-form">
                <input type="hidden" name="action" value="sw_jednoduche_ceniky_save">
                <?php wp_nonce_field(self::NONCE_KEY, '_wpnonce'); ?>

                <div class="swjc-toolbar swjc-card">
                    <div>
                        <h2><?php echo esc_html__('Správa ceníků', 'sw-jednoduche-ceniky'); ?></h2>
                        <p class="swjc-muted"><?php echo esc_html__('Můžete vytvořit čistý ceník nebo duplikovat existující a upravit si jen jazyk, měnu nebo text před cenou.', 'sw-jednoduche-ceniky'); ?></p>
                    </div>
                    <div class="swjc-toolbar__actions">
                        <button type="button" class="button button-secondary" id="swjc-add-list"><?php echo esc_html__('Přidat prázdný ceník', 'sw-jednoduche-ceniky'); ?></button>
                        <select id="swjc-duplicate-source" class="swjc-duplicate-source">
                            <option value=""><?php echo esc_html__('Vyberte ceník k duplikaci', 'sw-jednoduche-ceniky'); ?></option>
                            <?php foreach ($lists as $toolbar_list) : ?>
                                <option value="<?php echo esc_attr($toolbar_list['id']); ?>"><?php echo esc_html($toolbar_list['title'] !== '' ? $toolbar_list['title'] : __('Ceník bez názvu', 'sw-jednoduche-ceniky')); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button button-secondary" id="swjc-duplicate-list"><?php echo esc_html__('Duplikovat vybraný ceník', 'sw-jednoduche-ceniky'); ?></button>
                        <button type="submit" class="button button-primary"><?php echo esc_html__('Uložit změny', 'sw-jednoduche-ceniky'); ?></button>
                    </div>
                </div>

                <div id="swjc-lists" class="swjc-lists">
                    <?php foreach ($lists as $index => $list) : ?>
                        <?php $this->render_list($list, $index); ?>
                    <?php endforeach; ?>
                </div>

                <textarea name="swjc_payload" id="swjc-payload" class="swjc-hidden"></textarea>
            </form>
        </div>

        <script type="text/template" id="tpl-swjc-list">
            <?php $this->render_list([
                'id' => 'list___NEW__',
                'title' => '',
                'key' => '1',
                'currency' => 'Kč',
                'from_label' => 'od',
                'order' => 0,
                'categories' => [],
            ], '__NEW__', true); ?>
        </script>

        <script type="text/template" id="tpl-swjc-category">
            <?php $this->render_category([
                'id' => 'cat___NEW__',
                'title' => '',
                'order' => 0,
                'items' => [],
            ], '__LISTID__', '__NEW__', true); ?>
        </script>

        <script type="text/template" id="tpl-swjc-item">
            <?php $this->render_item([
                'id' => 'item___NEW__',
                'name' => '',
                'note' => '',
                'price' => '',
                'show_from' => 1,
                'order' => 0,
            ], '__LISTID__', '__CATID__', '__NEW__', true); ?>
        </script>
        <?php
    }

    private function render_list($list, $index = 0, $is_template = false) {
        $list_id    = esc_attr($list['id']);
        $title      = esc_attr($list['title']);
        $key        = esc_attr($list['key'] !== '' ? $list['key'] : '1');
        $currency   = esc_attr($list['currency']);
        $from_label = esc_attr($list['from_label']);
        $summary_title = $list['title'] !== '' ? $list['title'] : __('Ceník bez názvu', 'sw-jednoduche-ceniky');
        ?>
        <details class="swjc-list swjc-card swjc-accordion" data-list-id="<?php echo $list_id; ?>">
            <summary class="swjc-list__top">
                <div class="swjc-list__heading">
                    <span class="dashicons dashicons-move swjc-handle" aria-hidden="true"></span>
                    <div>
                        <h3><?php echo esc_html($summary_title); ?></h3>
                        <p class="swjc-muted"><?php echo esc_html(sprintf(__('Shortcode klíč: %s', 'sw-jednoduche-ceniky'), $key)); ?></p>
                    </div>
                </div>
                <div class="swjc-list__actions">
                    <button type="button" class="button button-secondary swjc-add-category"><?php echo esc_html__('Přidat kategorii', 'sw-jednoduche-ceniky'); ?></button>
                    <button type="button" class="button button-secondary swjc-duplicate-this-list"><?php echo esc_html__('Duplikovat ceník', 'sw-jednoduche-ceniky'); ?></button>
                    <button type="button" class="button button-link-delete swjc-delete-list"><?php echo esc_html__('Smazat ceník', 'sw-jednoduche-ceniky'); ?></button>
                </div>
            </summary>

            <div class="swjc-list__body">
                <div class="swjc-list__settings">
                    <div class="swjc-field">
                        <label><?php echo esc_html__('Název ceníku', 'sw-jednoduche-ceniky'); ?></label>
                        <input type="text" class="swjc-list__title" value="<?php echo $title; ?>" placeholder="<?php echo esc_attr__('Např. Český ceník', 'sw-jednoduche-ceniky'); ?>">
                    </div>
                    <div class="swjc-field">
                        <label><?php echo esc_html__('Klíč pro shortcode', 'sw-jednoduche-ceniky'); ?></label>
                        <input type="text" class="swjc-list__key" value="<?php echo $key; ?>" placeholder="<?php echo esc_attr__('např. 1, cz nebo en', 'sw-jednoduche-ceniky'); ?>">
                        <p class="description"><?php echo esc_html__('Použije se v shortcode jako [sw_cenik key="1"]. Doporučený formát: číslo nebo malá písmena bez diakritiky.', 'sw-jednoduche-ceniky'); ?></p>
                    </div>
                    <div class="swjc-field swjc-field--small">
                        <label><?php echo esc_html__('Měna', 'sw-jednoduche-ceniky'); ?></label>
                        <input type="text" class="swjc-list__currency" value="<?php echo $currency; ?>" placeholder="Kč">
                    </div>
                    <div class="swjc-field swjc-field--small">
                        <label><?php echo esc_html__('Text před cenou', 'sw-jednoduche-ceniky'); ?></label>
                        <input type="text" class="swjc-list__from" value="<?php echo $from_label; ?>" placeholder="<?php echo esc_attr__('např. od', 'sw-jednoduche-ceniky'); ?>">
                    </div>
                </div>

                <div class="swjc-categories">
                    <?php foreach ($list['categories'] as $cat_index => $cat) : ?>
                        <?php $this->render_category($cat, $list_id, $cat_index); ?>
                    <?php endforeach; ?>
                </div>

                <div class="swjc-shortcode-box">
                    <strong><?php echo esc_html__('Shortcode:', 'sw-jednoduche-ceniky'); ?></strong>
                    <code><?php echo esc_html('[sw_cenik key="' . $key . '"]'); ?></code>
                </div>
            </div>
        </details>
        <?php
    }

    private function render_category($cat, $list_id, $index = 0, $is_template = false) {
        $cat_id = esc_attr($cat['id']);
        $title  = esc_attr($cat['title']);
        $summary_title = $cat['title'] !== '' ? $cat['title'] : __('Kategorie bez názvu', 'sw-jednoduche-ceniky');
        ?>
        <details class="swjc-category swjc-accordion" data-cat-id="<?php echo $cat_id; ?>">
            <summary class="swjc-category__header">
                <div class="swjc-category__heading">
                    <span class="dashicons dashicons-move swjc-handle" aria-hidden="true"></span>
                    <strong class="swjc-category__summary-title"><?php echo esc_html($summary_title); ?></strong>
                </div>
                <div class="swjc-category__actions">
                    <button type="button" class="button button-secondary swjc-add-item"><?php echo esc_html__('Přidat položku', 'sw-jednoduche-ceniky'); ?></button>
                    <button type="button" class="button button-link-delete swjc-delete-category"><?php echo esc_html__('Smazat kategorii', 'sw-jednoduche-ceniky'); ?></button>
                </div>
            </summary>
            <div class="swjc-category__body">
                <div class="swjc-field swjc-field--category-title">
                    <label><?php echo esc_html__('Název kategorie', 'sw-jednoduche-ceniky'); ?></label>
                    <input type="text" class="swjc-category__title" value="<?php echo $title; ?>" placeholder="<?php echo esc_attr__('Název kategorie', 'sw-jednoduche-ceniky'); ?>">
                </div>
                <table class="widefat striped swjc-table">
                    <thead>
                        <tr>
                            <th style="width:36px"></th>
                            <th><?php echo esc_html__('Služba + poznámka', 'sw-jednoduche-ceniky'); ?></th>
                            <th style="width:220px"><?php echo esc_html__('Cena', 'sw-jednoduche-ceniky'); ?></th>
                            <th style="width:110px"><?php echo esc_html__('Akce', 'sw-jednoduche-ceniky'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="swjc-items">
                        <?php foreach ($cat['items'] as $item_index => $item) : ?>
                            <?php $this->render_item($item, $list_id, $cat_id, $item_index); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
        <?php
    }

    private function render_item($item, $list_id, $cat_id, $index = 0, $is_template = false) {
        $item_id = esc_attr($item['id']);
        $name    = esc_attr($item['name']);
        $note    = esc_attr($item['note']);
        $price   = esc_attr($item['price']);
        $show_from = array_key_exists('show_from', $item) ? !empty($item['show_from']) : true;
        ?>
        <tr class="swjc-item" data-item-id="<?php echo $item_id; ?>">
            <td class="swjc-item__drag"><span class="dashicons dashicons-menu swjc-handle" aria-hidden="true"></span></td>
            <td>
                <input type="text" class="swjc-item__name" value="<?php echo $name; ?>" placeholder="<?php echo esc_attr__('Název služby', 'sw-jednoduche-ceniky'); ?>">
                <input type="text" class="swjc-item__note" value="<?php echo $note; ?>" placeholder="<?php echo esc_attr__('Poznámka (volitelně)', 'sw-jednoduche-ceniky'); ?>">
            </td>
            <td>
                <input type="text" class="swjc-item__price" value="<?php echo $price; ?>" placeholder="<?php echo esc_attr__('Např. 1500 nebo dle dohody', 'sw-jednoduche-ceniky'); ?>">
                <label class="swjc-item__show-from"><input type="checkbox" class="swjc-item__show-from-toggle" <?php checked($show_from); ?>> <?php echo esc_html__('Zobrazit text před cenou', 'sw-jednoduche-ceniky'); ?></label>
            </td>
            <td class="swjc-item__actions">
                <button type="button" class="button button-link-delete swjc-delete-item"><?php echo esc_html__('Smazat', 'sw-jednoduche-ceniky'); ?></button>
            </td>
        </tr>
        <?php
    }

    public function handle_save() {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        check_admin_referer(self::NONCE_KEY);

        $payload = isset($_POST['swjc_payload']) ? wp_unslash($_POST['swjc_payload']) : '';
        $lists = json_decode($payload, true);
        if (!is_array($lists)) {
            $lists = [];
        }

        $sanitized = $this->sanitize_payload($lists);
        update_option(self::OPTION_KEY, $sanitized, false);

        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    private function sanitize_payload($lists) {
        $out = [];
        $list_order = 0;
        $used_keys = [];

        foreach ($lists as $list) {
            if (!is_array($list)) {
                continue;
            }

            $list_id = isset($list['id']) ? sanitize_text_field($list['id']) : $this->new_id('list');
            if (strpos($list_id, 'list___NEW__') === 0) {
                $list_id = $this->new_id('list');
            }

            $title = isset($list['title']) ? sanitize_text_field($list['title']) : '';
            if ($title === '') {
                continue;
            }

            $key = isset($list['key']) ? sanitize_title($list['key']) : sanitize_title($title);
            if ($key === '') {
                $key = (string) ($list_order + 1);
            }
            $base_key = $key;
            $i = 2;
            while (in_array($key, $used_keys, true)) {
                $key = $base_key . '-' . $i;
                $i++;
            }
            $used_keys[] = $key;

            $currency = isset($list['currency']) ? sanitize_text_field($list['currency']) : 'Kč';
            $from_label = isset($list['from_label']) ? sanitize_text_field($list['from_label']) : '';

            $categories_out = [];
            $cat_order = 0;
            $categories = isset($list['categories']) && is_array($list['categories']) ? $list['categories'] : [];
            foreach ($categories as $cat) {
                if (!is_array($cat)) {
                    continue;
                }
                $cat_id = isset($cat['id']) ? sanitize_text_field($cat['id']) : $this->new_id('cat');
                if (strpos($cat_id, 'cat___NEW__') === 0) {
                    $cat_id = $this->new_id('cat');
                }
                $cat_title = isset($cat['title']) ? sanitize_text_field($cat['title']) : '';
                if ($cat_title === '') {
                    continue;
                }

                $items_out = [];
                $item_order = 0;
                $items = isset($cat['items']) && is_array($cat['items']) ? $cat['items'] : [];
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $item_id = isset($item['id']) ? sanitize_text_field($item['id']) : $this->new_id('item');
                    if (strpos($item_id, 'item___NEW__') === 0) {
                        $item_id = $this->new_id('item');
                    }
                    $name  = isset($item['name']) ? sanitize_text_field($item['name']) : '';
                    $note  = isset($item['note']) ? sanitize_text_field($item['note']) : '';
                    $price = isset($item['price']) ? sanitize_text_field($item['price']) : '';

                    if ($name === '' && $note === '' && $price === '') {
                        continue;
                    }

                    $items_out[] = [
                        'id'        => $item_id,
                        'name'      => $name,
                        'note'      => $note,
                        'price'     => $price,
                        'show_from' => !empty($item['show_from']) ? 1 : 0,
                        'order'     => $item_order++,
                    ];
                }

                $categories_out[] = [
                    'id'    => $cat_id,
                    'title' => $cat_title,
                    'order' => $cat_order++,
                    'items' => $items_out,
                ];
            }

            $out[] = [
                'id'         => $list_id,
                'title'      => $title,
                'key'        => $key,
                'currency'   => $currency,
                'from_label' => $from_label,
                'order'      => $list_order++,
                'categories' => $categories_out,
            ];
        }

        return $out;
    }

    private function format_price($raw, $currency = 'Kč', $from_label = '', $show_from = true) {
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^\d[\d\s.,]*$/u', $raw)) {
            $clean = preg_replace('/[^\d]/', '', $raw);
            if ($clean === '') {
                return '';
            }
            $value = (int) $clean;
            $nbsp = "\xc2\xa0";
            $formatted = number_format($value, 0, '', $nbsp);

            $parts = [];
            if ($show_from && $from_label !== '') {
                $parts[] = $from_label;
            }
            $parts[] = $formatted;
            if ($currency !== '') {
                $parts[] = $currency;
            }

            return implode($nbsp, $parts);
        }

        return $raw;
    }

    public function shortcode($atts = []) {
        $lists = $this->get_data();
        if (empty($lists)) {
            return '';
        }

        $atts = shortcode_atts([
            'key' => '',
            'id'  => '',
        ], $atts, 'sw_cenik');

        $selected = null;
        foreach ($lists as $list) {
            if ($atts['id'] !== '' && $list['id'] === $atts['id']) {
                $selected = $list;
                break;
            }
            if ($atts['key'] !== '' && $list['key'] === sanitize_title($atts['key'])) {
                $selected = $list;
                break;
            }
        }

        if ($selected === null) {
            $selected = $lists[0];
        }

        $css_path = plugin_dir_path(__FILE__) . 'assets/frontend.css';
        wp_enqueue_style(
            'sw-jednoduche-ceniky-frontend',
            plugin_dir_url(__FILE__) . 'assets/frontend.css',
            [],
            file_exists($css_path) ? (string) filemtime($css_path) : '1.1.0'
        );

        ob_start();
        echo '<div class="swjc-price-list">';
        foreach ($selected['categories'] as $category) {
            echo '<section class="swjc-price-list__section">';
            echo '<h2 class="swjc-price-list__title">' . esc_html($category['title']) . '</h2>';
            echo '<div class="table-responsive swjc-price-list__tablewrap">';
            echo '<table class="table swjc-price-list__table">';
            echo '<colgroup><col style="width:70%"><col style="width:30%"></colgroup>';
            echo '<tbody>';
            foreach ($category['items'] as $item) {
                echo '<tr>';
                echo '<td class="swjc-price-list__col1">';
                echo '<div class="swjc-price-list__service">' . esc_html($item['name']) . '</div>';
                if (!empty($item['note'])) {
                    echo '<div class="small swjc-price-list__note">' . esc_html($item['note']) . '</div>';
                }
                echo '</td>';
                echo '<td class="swjc-price-list__col2">' . esc_html($this->format_price($item['price'], $selected['currency'], $selected['from_label'], !empty($item['show_from']))) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div></section>';
        }
        echo '</div>';
        return ob_get_clean();
    }
}

new SW_Jednoduche_Ceniky();
