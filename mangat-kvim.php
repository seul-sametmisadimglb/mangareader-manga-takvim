<?php
/*
Plugin Name: MangaReader Takvim Haftalık
Description: Haftanın günlerine göre manga takvimi + Durum etiketleri
Version: 7.0
Author: Seul
*/

if (!defined('ABSPATH')) exit;

class MangaWeeklyCalendar {

    private $post_type = 'manga';
    private $nonce_key = 'manga_weekly_nonce';
    private $week_days = [
        'monday'    => 'Pazartesi',
        'tuesday'   => 'Salı',
        'wednesday' => 'Çarşamba',
        'thursday'  => 'Perşembe',
        'friday'    => 'Cuma',
        'saturday'  => 'Cumartesi',
        'sunday'    => 'Pazar'
    ];

    public function __construct() {
        // Admin
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_ajax_save_manga_day', [$this, 'save_manga_day']);
        
        // Frontend
        add_shortcode('manga_weekly', [$this, 'render_calendar']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        
        // Görsel Düzenlemeler
        add_action('admin_head', [$this, 'admin_styles']);
        add_action('wp_head', [$this, 'frontend_styles']);
        add_action('admin_footer', [$this, 'admin_scripts']);
    }

    // 1. ADMIN PANELİ
    public function admin_menu() {
        add_menu_page(
            'Manga Günleri',
            'Manga Günleri',
            'manage_options',
            'manga-weekly-calendar',
            [$this, 'admin_page'],
            'dashicons-calendar-alt',
            6
        );
    }

    public function admin_page() {
        $mangas = get_posts([
            'post_type' => $this->post_type,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        ?>
        <div class="wrap">
            <h1>Manga Takvim</h1>
            <div class="manga-list-container">
                <?php foreach ($mangas as $manga): 
                    $day = get_post_meta($manga->ID, '_manga_day', true);
                    $status = get_post_meta($manga->ID, '_manga_status', true);
                    $cover = get_the_post_thumbnail_url($manga->ID, 'thumbnail');
                ?>
                <div class="manga-item" data-id="<?= $manga->ID ?>">
                    <img src="<?= esc_url($cover) ?>" class="manga-cover">
                    <h3><?= esc_html($manga->post_title) ?></h3>
                    <div class="schedule-controls">
                        <select class="day-selector">
                            <option value="">Gün Seçin</option>
                            <?php foreach ($this->week_days as $key => $name): ?>
                                <option value="<?= $key ?>" <?= selected($day, $key) ?>>
                                    <?= $name ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select class="status-selector">
                            <option value="">Durum</option>
                            <option value="yayinlandi" <?= selected($status, 'yayınlandı') ?>>Yayınlandı</option>
                            <option value="ertelendi" <?= selected($status, 'ertelendi') ?>>Ertelendi</option>
                            <option value="iptal" <?= selected($status, 'iptal') ?>>İptal</option>
                        </select>
                        <button class="button save-day">Kaydet</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    // 2. VERİ İŞLEMLERİ
    public function save_manga_day() {
        check_ajax_referer($this->nonce_key, 'nonce');
        
        $post_id = absint($_POST['post_id']);
        $day = sanitize_text_field($_POST['day']);
        $status = sanitize_text_field($_POST['status']);
        
        update_post_meta($post_id, '_manga_day', $day);
        update_post_meta($post_id, '_manga_status', $status);
        wp_send_json_success('Güncellendi!');
    }

    // 3. FRONTEND TAKVİM
    public function render_calendar() {
        ob_start(); ?>
        <div class="weekly-calendar">
            <?php foreach ($this->week_days as $key => $day): 
                $mangas = $this->get_mangas_by_day($key);
            ?>
            <div class="day-column <?= $key ?>">
                <h3 class="day-title"><?= $day ?></h3>
                <div class="manga-list">
                    <?php foreach ($mangas as $manga): 
                        $cover = get_the_post_thumbnail_url($manga->ID, 'medium');
                        $status = get_post_meta($manga->ID, '_manga_status', true);
                    ?>
                    <div class="manga-card">
                        <div class="manga-status-container">
                            <?php if($status): ?>
                            <div class="status-badge status-<?= sanitize_title($status) ?>">
                                <?= esc_html($status) ?>
                            </div>
                            <?php endif; ?>
                            <img src="<?= esc_url($cover) ?>" class="manga-cover">
                        </div>
                        <h4><?= esc_html($manga->post_title) ?></h4>
                        <a href="<?= get_permalink($manga->ID) ?>" class="read-button">Oku</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_mangas_by_day($day) {
        return get_posts([
            'post_type' => $this->post_type,
            'posts_per_page' => -1,
            'meta_key' => '_manga_day',
            'meta_value' => $day,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
    }

    // 4. STİL VE SCRİPTLER
    public function admin_styles() { ?>
        <style>
            .manga-list-container {
                display: grid;
                gap: 15px;
            }
            .manga-item {
                background: #fff;
                border: 1px solid #ddd;
                padding: 15px;
                border-radius: 8px;
                display: grid;
                grid-template-columns: 100px 1fr auto;
                gap: 20px;
            }
            .schedule-controls {
                display: flex;
                gap: 10px;
                align-items: center;
            }
            .status-selector {
                padding: 6px 12px;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
        </style>
    <?php }

    public function frontend_styles() { ?>
        <style>
            /* DURUM ETİKETLERİ */
            .manga-status-container {
                position: relative;
                width: 100%;
                margin-bottom: 10px;
            }
            .status-badge {
                position: absolute;
                top: 10px;
                left: 10px;
                padding: 6px 12px;
                border-radius: 15px;
                font-size: 12px;
                font-weight: bold;
                text-transform: uppercase;
                z-index: 2;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            }
            .status-yayinlandi { background: #2ecc71; color: #fff; }
            .status-ertelendi { background: #f39c12; color: #fff; }
            .status-iptal { background: #e74c3c; color: #fff; }

            /* DİĞER STİLLER */
            .weekly-calendar {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                padding: 20px;
                background: #0a0a0a;
            }
    
            /* MANGALAR İÇİN KUTULAR */
            .manga-card {
                background: #111;
                border-radius: 8px;
                padding: 15px;
                border: 1px solid #252525;
                display: flex;
                flex-direction: column;
                min-height: 220px;
                margin-bottom: 15px;/* Sabit minimum yükseklik */
            }
    
            /* RESİM KONTEYNIRI */
            .image-container {
                width: 100%;
                height: 120px;
                margin-bottom: 12px;
                display: flex;
                justify-content: center;
                align-items: center;
            }
    
            /* RESİMLER */
            .manga-cover {
                width: 90px;
                height: 120px;
                object-fit: cover;
                border-radius: 4px;
                border: 1px solid #333;
            }
    
            /* METİN ALANI */
            .manga-info {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
    
            .manga-info h4 {
                color: #eee;
                font-size: 12px;
                margin: 0 0 10px 0;
                line-height: 1.3;
                min-height: 32px;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
    
            /* BUTON */
            .read-button {
                display: block;
                background: #e74c3c;
                color: white !important;
                padding: 8px;
                font-size: 12px;
                border-radius: 4px;
                text-align: center;
                text-decoration: none;
                margin-top: auto; /* Butonu en alta sabitle */
                border: none;
                transition: background 0.2s;
            }
    
            .read-button:hover {
                background: #c0392b;
            }
    
            /* GÜN BAŞLIKLARI */
            .day-title {
                color: #fff;
                font-size: 14px;
                padding: 10px;
                background: #1a1a1a;
                margin: -15px -15px 15px -15px;
                border-radius: 8px 8px 0 0;
            }
        </style>
    <?php }
    public function admin_scripts() { ?>
        <script>
            jQuery(document).ready(function($) {
                $('.save-day').click(function() {
                    var $btn = $(this);
                    var $item = $btn.closest('.manga-item');
                    
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'save_manga_day',
                            post_id: $item.data('id'),
                            day: $item.find('.day-selector').val(),
                            status: $item.find('.status-selector').val(),
                            nonce: '<?= wp_create_nonce($this->nonce_key) ?>'
                        },
                        beforeSend: function() {
                            $btn.text('Kaydediliyor...');
                        },
                        success: function() {
                            $btn.text('Kaydedildi!');
                            setTimeout(() => $btn.text('Kaydet'), 2000);
                        }
                    });
                });
            });
        </script>
    <?php }

    public function admin_assets($hook) {
        if ('toplevel_page_manga-weekly-calendar' !== $hook) return;
        wp_enqueue_style('wp-jquery-ui-dialog');
    }

    public function frontend_assets() {
        wp_enqueue_style('dashicons');
    }
}

new MangaWeeklyCalendar();