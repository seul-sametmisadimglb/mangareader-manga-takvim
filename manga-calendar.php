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
        /* MANGA CARD - MİNİMAL MODERN KOYU TEMA */
        .weekly-calendar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            padding: 25px;
            background: #121212;
        }
  
  .page img {
    width: 100%;
    height: auto;
    margin: 0 auto;
    margin-bottom: 10px;
    display: block;
    text-align: center;
    aspect-ratio: 3 / 4;
}


        /* Gün başlıkları */
        .day-title {
            color: #fff;
            font-size: 16px;
            font-weight: 500;
            padding: 12px 15px;
            background: #1a1a1a;
            margin: 0 0 15px 0;
            border-left: 3px solid #e74c3c;
            letter-spacing: 1px;
            border-radius: 0 10px 10px 0;
        }

        /* Manga kartı */
        .manga-card {
            background: #1a1a1a;
            border: 1px solid #333;
            display: flex;
            flex-direction: column;
            min-height: 280px;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .manga-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        /* Manga başlığı */
        .manga-card h4 {
            color: #fff;
            padding: 15px;
            margin: 0;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.4;
            min-height: auto;
            text-align: center;
            background: #1a1a1a;
            border-bottom: 1px solid #333;
        }



        /* Manga kapak ve durum konteynırı */
        .manga-status-container {
            position: relative;
            width: 100%;
            padding: 15px;
            display: flex;
            justify-content: center;
            background: #1a1a1a;
            border-radius: 10px;
        }

        /* Manga kapak resmi */
        .manga-cover {
            width: 120px;
            height: 160px;
            object-fit: cover;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            border-radius: 10px;
        }

        .manga-card:hover .manga-cover {
            transform: scale(1.03);
        }

        /* Durum etiketi */
        .status-badge {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 5px 12px;
            border-radius: 2px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            z-index: 2;
           border-radius: 10px;
        }

        .status-yayinlandi { 
            background: #2ecc71; 
            color: #fff; 
        }

        .status-ertelendi { 
            background: #f39c12; 
            color: #fff; 
        }

        .status-iptal { 
            background: #e74c3c; 
            color: #fff; 
        }

        /* Oku butonu */
        .read-button {
            display: block;
            background: #e74c3c;
            color: white !important;
            padding: 10px 15px;
            font-size: 13px;
            text-align: center;
            text-decoration: none;
            margin: 10px 15px 15px;
            border: none;
            transition: background 0.2s;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
            border-radius: 10px;
        }

        .read-button:hover {
            background: #c0392b;
        }

        /* Responsive düzenlemeler */
        @media (max-width: 768px) {
            .weekly-calendar {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                padding: 15px;
            }
            
            .manga-cover {
                width: 100px;
                height: 140px;
            }
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