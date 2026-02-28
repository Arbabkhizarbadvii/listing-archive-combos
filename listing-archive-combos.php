<?php
/**
 * Plugin Name: Listing Archive Combos (Region × Category)
 * Description: Admin UI to define content per (Region × Category) combination for job_listing archives.
 * Version: 1.5.1
 * Author: ARBAB KHIZAR
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: listing-archive-combos
 */

if (!defined('ABSPATH')) exit;

class JL_Archive_Combos_Plugin {
    const OPTION_KEY    = 'jl_region_category_content';
    const NONCE_ACTION  = 'jl_archive_combos_save';
    const PAGE_SLUG     = 'jl-archive-combos';
    const CAPABILITY    = 'manage_options';
    const REGION_TAX    = 'job_listing_region';
    const CATEGORY_TAX  = 'job_listing_category';
    const ASSET_VERSION = '1.5.1';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_setting']);
        add_action('admin_init', [$this, 'handle_redirect_after_save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('option_page_capability_' . self::PAGE_SLUG, [$this, 'set_option_capability']);

        add_action('init', function () {
            if (!function_exists('jl_the_archive_combo_sections_from_query')) {
                function jl_the_archive_combo_sections_from_query($print_schema = true) {
                    JL_Archive_Combos_Plugin::the_sections_from_query($print_schema);
                }
            }
            if (!function_exists('jl_get_archive_combo')) {
                function jl_get_archive_combo($region_id, $category_id) {
                    return JL_Archive_Combos_Plugin::get_combo($region_id, $category_id);
                }
            }
            if (!function_exists('jl_render_archive_combo_sections')) {
                function jl_render_archive_combo_sections($row, $print_schema = true) {
                    JL_Archive_Combos_Plugin::render_sections($row, $print_schema);
                }
            }
        });
    }
    public function add_menu() {
        add_management_page(
            'Listing Archive Combos',
            'Listing Archive Combos',
            'edit_posts',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    private function can_manage_combos() {
        $user = wp_get_current_user();
        return current_user_can('manage_options') || in_array('wpseo_manager', (array) $user->roles);
    }

    public function set_option_capability($capability) {
        return 'edit_posts';
    }

    public function handle_redirect_after_save() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true' && isset($_GET['page']) && $_GET['page'] === self::PAGE_SLUG) {
            $current_page = isset($_POST['jl_current_page']) ? max(1, intval($_POST['jl_current_page'])) : 1;
            $redirect_url = add_query_arg([
                'page' => self::PAGE_SLUG,
                'paged' => $current_page,
                'updated' => 'true'
            ], admin_url('tools.php'));
            wp_redirect($redirect_url);
            exit;
        }
    }

    public function register_setting() {
        register_setting(self::PAGE_SLUG, self::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_rows'],
            'default'           => [],
        ]);
    }

    public function enqueue_assets($hook) {
        $expected = 'tools_page_' . self::PAGE_SLUG;
        if ($hook !== $expected) return;

        wp_enqueue_media();
        wp_enqueue_editor();

        wp_enqueue_style(
            'jl-archive-combos-css',
            plugins_url('assets/admin.css', __FILE__),
            [],
            self::ASSET_VERSION
        );

        wp_enqueue_script(
            'jl-archive-combos-js',
            plugins_url('assets/admin.js', __FILE__),
            ['jquery'],
            self::ASSET_VERSION,
            true
        );
    }
    public function render_page() {
        if (!$this->can_manage_combos()) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $rows = get_option(self::OPTION_KEY, []);
        if (!is_array($rows)) $rows = [];

        $region_options   = $this->build_term_options(self::REGION_TAX);
        $category_options = $this->build_term_options(self::CATEGORY_TAX);
        $region_count     = $this->get_region_count();

        $row_tpl_path = plugin_dir_path(__FILE__) . 'templates/row-template.html';
        $row_template = file_exists($row_tpl_path) ? file_get_contents($row_tpl_path) : '';

        $view_path = plugin_dir_path(__FILE__) . 'includes/admin-page.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            echo '<div class="wrap"><h1>Listing Archive Combos</h1><p>Missing admin-page.php</p></div>';
        }
    }

    private function build_term_options($taxonomy) {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        if (is_wp_error($terms)) $terms = [];
        $html = '<option value="0">-- Select --</option>';
        foreach ($terms as $t) {
            $html .= '<option value="' . (int)$t->term_id . '">' . esc_html($t->name) . '</option>';
        }
        return $html;
    }

    private function mark_selected_in_options($options_html, $selected_value) {
        if (!$selected_value) return $options_html;
        return preg_replace(
            '/(<option\s+value="' . preg_quote((string)$selected_value, '/') . '")([^>]*>)/',
            '$1 selected="selected"$2',
            $options_html,
            1
        );
    }

    private function get_region_count() {
        $count = wp_count_terms(self::REGION_TAX, ['hide_empty' => false]);
        if (is_wp_error($count)) return 0;
        return (int) $count;
    }

    public function render_row_existing($index, $row, $region_options, $category_options) {
        $region   = isset($row['region']) ? (int)$row['region'] : 0;
        $category = isset($row['category']) ? (int)$row['category'] : 0;

        $region_opts   = $this->mark_selected_in_options($region_options, $region);
        $category_opts = $this->mark_selected_in_options($category_options, $category);

        $s1_heading = isset($row['s1_heading']) ? (string)$row['s1_heading'] : '';
        $s1_content = isset($row['s1_content']) ? (string)$row['s1_content'] : '';

        $s2 = wp_parse_args(isset($row['s2']) ? $row['s2'] : [], [
            'coworking'       => ['image' => 0, 'content' => ''],
            'day_offices'     => ['image' => 0, 'content' => ''],
            'meeting_event'   => ['image' => 0, 'content' => ''],
            'private_offices' => ['image' => 0, 'content' => ''],
        ]);
        $s2_intro = isset($row['s2_intro']) ? (string)$row['s2_intro'] : '';

        $faqs_raw = (isset($row['faqs']) && is_array($row['faqs'])) ? $row['faqs'] : [];
        $faqs = [];
        foreach ($faqs_raw as $faq) {
            $q = isset($faq['q']) ? trim($faq['q']) : '';
            $a = isset($faq['a']) ? trim($faq['a']) : '';
            if ($q !== '' || $a !== '') {
                $faqs[] = $faq;
            }
        }

        // Get region and category names for the header
        $region_obj = $region > 0 ? get_term($region, self::REGION_TAX) : null;
        $category_obj = $category > 0 ? get_term($category, self::CATEGORY_TAX) : null;
        $region_display = $region_obj ? $region_obj->name : 'Not Selected';
        $category_display = $category_obj ? $category_obj->name : 'Not Selected';

        ob_start(); ?>
<tr class="jl-row-header" data-index="<?php echo esc_attr($index); ?>">
  <td colspan="4" style="background: #f6f7f7; padding: 12px; cursor: pointer; font-weight: 600; border-left: 4px solid #2271b1;">
    <div class="jl-header-content" style="display: flex; justify-content: space-between; align-items: center;">
      <div class="jl-header-info">
        <span class="jl-toggle-icon" style="display: inline-block; width: 20px; transition: transform 0.3s;">▼</span>
        <span class="jl-combo-label" style="font-size: 14px;">Region: <strong><?php echo esc_html($region_display); ?></strong> | Category: <strong><?php echo esc_html($category_display); ?></strong></span>
      </div>
      <a href="#" class="delete-row" style="color: #b32d2e; text-decoration: none;">Remove</a>
    </div>
  </td>
</tr>
<tr class="jl-row jl-row-content" data-index="<?php echo esc_attr($index); ?>">
  <td class="col-small">
    <select name="<?php echo esc_attr(self::OPTION_KEY . '[' . $index . '][region]'); ?>">
      <?php echo $region_opts; ?>
    </select>
  </td>
  <td class="col-small">
    <select name="<?php echo esc_attr(self::OPTION_KEY . '[' . $index . '][category]'); ?>">
      <?php echo $category_opts; ?>
    </select>
  </td>
  <td>
    <fieldset class="jl-box">
      <legend><strong>Section 1</strong> — Heading + Content (WYSIWYG)</legend>
      <p>
        <label>Heading</label><br>
        <input type="text" class="regular-text" style="width:100%;"
               name="<?php echo esc_attr(self::OPTION_KEY . '['.$index.'][s1_heading]'); ?>"
               value="<?php echo esc_attr($s1_heading); ?>">
      </p>
      <div class="jl-editor-wrap">
        <?php
          wp_editor(
            $s1_content,
            's1_content_'.$index,
            [
              'textarea_name' => self::OPTION_KEY.'['.$index.'][s1_content]',
              'textarea_rows' => 8,
              'media_buttons' => true,
              'teeny'         => false,
              'quicktags'     => true,
            ]
          );
        ?>
      </div>
    </fieldset>

    <fieldset class="jl-box">
  <legend><strong>Section 2</strong> — Intro (WYSIWYG) + 4 Cards</legend>

  <div class="jl-editor-wrap" style="margin-bottom:12px;">
    <?php
      wp_editor(
        $s2_intro,
        's2_intro_'.$index,
        [
          'textarea_name' => self::OPTION_KEY.'['.$index.'][s2_intro]',
          'textarea_rows' => 6,
          'media_buttons' => true,
          'teeny'         => true,
          'quicktags'     => true,
        ]
      );
    ?>
  </div>

  <?php
    $cards = [
      'coworking'       => 'Coworking',
      'day_offices'     => 'Day Offices',
      'meeting_event'   => 'Meeting &amp; Event Spaces',
      'private_offices' => 'Private Offices',
    ];
        foreach ($cards as $key => $label):
          $img_id   = isset($s2[$key]['image']) ? (int)$s2[$key]['image'] : 0;
          $content  = isset($s2[$key]['content']) ? (string)$s2[$key]['content'] : '';
          $field_ns = self::OPTION_KEY.'['.$index.'][s2]['.$key.']';
          $ed_id    = 's2_'.$key.'_'.$index;
          $thumb    = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : '';
      ?>
      <div class="jl-card">
        <h4><?php echo esc_html($label); ?></h4>
        <div class="jl-media-field" data-target="<?php echo esc_attr($field_ns.'[image]'); ?>">
          <input type="hidden" class="jl-image-id" name="<?php echo esc_attr($field_ns.'[image]'); ?>" value="<?php echo (int)$img_id; ?>">
          <div class="jl-image-preview">
            <?php if ($thumb): ?>
              <img src="<?php echo esc_url($thumb); ?>" alt="" />
            <?php endif; ?>
          </div>
          <p>
            <a href="#" class="button jl-pick-image">Select Image</a>
            <a href="#" class="button-link jl-remove-image" <?php if(!$img_id) echo 'style="display:none"'; ?>>Remove</a>
          </p>
        </div>

        <div class="jl-editor-wrap">
          <?php
            wp_editor(
              $content,
              $ed_id,
              [
                'textarea_name' => $field_ns.'[content]',
                'textarea_rows' => 6,
                'media_buttons' => true,
                'teeny'         => true,
                'quicktags'     => true,
              ]
            );
          ?>
        </div>
      </div>
      <?php endforeach; ?>
    </fieldset>

    <fieldset class="jl-box">
      <legend><strong>Section 3</strong> — FAQ (Question + WYSIWYG Answer)</legend>
      <table class="widefat jl-faqs-table" data-row-index="<?php echo esc_attr($index); ?>">
        <thead>
          <tr><th style="width:35%;">Question</th><th>Answer</th><th style="width:80px;"></th></tr>
        </thead>
        <tbody>
          <?php
          $fi = 0;
          if (!empty($faqs)):
            foreach ($faqs as $faq):
              $q = isset($faq['q']) ? $faq['q'] : '';
              $a = isset($faq['a']) ? $faq['a'] : '';
              $ans_ed_id = 'faq_'.$index.'_'.$fi;
          ?>
          <tr class="jl-faq-row">
            <td>
              <input type="text" style="width:100%;" name="<?php echo esc_attr(self::OPTION_KEY.'['.$index.'][faqs]['.$fi.'][q]'); ?>" value="<?php echo esc_attr($q); ?>">
            </td>
            <td class="jl-faq-answer">
              <?php
                wp_editor(
                  $a,
                  $ans_ed_id,
                  [
                    'textarea_name' => self::OPTION_KEY.'['.$index.'][faqs]['.$fi.'][a]',
                    'textarea_rows' => 4,
                    'media_buttons' => false,
                    'teeny'         => true,
                    'quicktags'     => true,
                  ]
                );
              ?>
            </td>
            <td class="row-actions"><a href="#" class="delete-faq">Remove</a></td>
          </tr>
          <?php
              $fi++;
            endforeach;
          endif;
          $ans_ed_id = 'faq_'.$index.'_'.$fi;
          ?>
          <tr class="jl-faq-row">
            <td>
              <input type="text" style="width:100%;" name="<?php echo esc_attr(self::OPTION_KEY.'['.$index.'][faqs]['.$fi.'][q]'); ?>" value="">
            </td>
            <td class="jl-faq-answer">
              <?php
                wp_editor(
                  '',
                  $ans_ed_id,
                  [
                    'textarea_name' => self::OPTION_KEY.'['.$index.'][faqs]['.$fi.'][a]',
                    'textarea_rows' => 4,
                    'media_buttons' => false,
                    'teeny'         => true,
                    'quicktags'     => true,
                  ]
                );
              ?>
            </td>
            <td class="row-actions"><a href="#" class="delete-faq">Remove</a></td>
          </tr>
        </tbody>
      </table>
      <p><a href="#" class="button jl-add-faq" data-row="<?php echo esc_attr($index); ?>">Add FAQ</a></p>
    </fieldset>
  </td>
  <td colspan="1"></td>
</tr>
<?php
        return ob_get_clean();
    }

    public function render_row_template_with_values($row_template, $index, $region_options, $category_options) {
        $region_opts   = $this->mark_selected_in_options($region_options, 0);
        $category_opts = $this->mark_selected_in_options($category_options, 0);
        $tpl = str_replace(['{{REGION_OPTIONS}}','{{CATEGORY_OPTIONS}}'], [$region_opts, $category_opts], $row_template);
        return str_replace('__INDEX__', (string)$index, $tpl);
    }

    private function clean_inline_styles($html) {
        if (empty($html)) return $html;
        
        // Remove problematic inline styles that cause white text
        $patterns = [
            '/color:\s*#fff(fff)?;?/i',
            '/color:\s*white;?/i',
            '/background-color:\s*transparent;?/i',
            '/font-family:\s*[\'"]?Century Gothic[\'"]?[^;]*;?/i',
            '/font-size:\s*11pt;?/i',
            '/font-weight:\s*400;?/i',
            '/font-style:\s*normal;?/i',
            '/font-variant:\s*normal;?/i',
            '/text-decoration:\s*none;?/i',
            '/vertical-align:\s*baseline;?/i',
        ];
        
        foreach ($patterns as $pattern) {
            $html = preg_replace($pattern, '', $html);
        }
        
        // Remove empty style attributes
        $html = preg_replace('/\s*style=""\s*/i', ' ', $html);
        $html = preg_replace('/\s*style="\s*"\s*/i', ' ', $html);
        $html = preg_replace('/\s*data-mce-style="[^"]*"\s*/i', ' ', $html);
        
        return $html;
    }

    public function sanitize_rows($input) {
        if (!isset($_POST['_jl_nonce']) || !wp_verify_nonce($_POST['_jl_nonce'], self::NONCE_ACTION)) {
            add_settings_error(
                self::PAGE_SLUG, 
                'nonce', 
                'Security check failed. Your session may have expired. Please try saving again.', 
                'error'
            );
            return is_array($input) ? $input : get_option(self::OPTION_KEY, []);
        }
        if (!$this->can_manage_combos()) {
            add_settings_error(
                self::PAGE_SLUG, 
                'cap', 
                'Insufficient permissions to save these settings.', 
                'error'
            );
            return get_option(self::OPTION_KEY, []);
        }

        // Check if user clicked the "Clean ALL White Text" button
        $force_clean_all = isset($_POST['jl_clean_all_styles']) && $_POST['jl_clean_all_styles'] === '1';
        
        $clean = []; $seen = [];
        $styles_cleaned = false;

        if (is_array($input)) {
            foreach ($input as $row) {
                $region   = isset($row['region']) ? (int)$row['region'] : 0;
                $category = isset($row['category']) ? (int)$row['category'] : 0;

                $s1_heading = isset($row['s1_heading']) ? sanitize_text_field($row['s1_heading']) : '';
                $s1_content = isset($row['s1_content']) ? wp_kses_post($row['s1_content']) : '';
                $s1_content_cleaned = $this->clean_inline_styles($s1_content);
                if ($s1_content !== $s1_content_cleaned) $styles_cleaned = true;
                $s1_content = $s1_content_cleaned;

                $s2_intro = isset($row['s2_intro']) ? wp_kses_post($row['s2_intro']) : '';
                $s2_intro_cleaned = $this->clean_inline_styles($s2_intro);
                if ($s2_intro !== $s2_intro_cleaned) $styles_cleaned = true;
                $s2_intro = $s2_intro_cleaned;
                
                $cards_in  = isset($row['s2']) && is_array($row['s2']) ? $row['s2'] : [];
                $cards_out = [];
                foreach (['coworking','day_offices','meeting_event','private_offices'] as $key) {
                    $img = isset($cards_in[$key]['image']) ? (int)$cards_in[$key]['image'] : 0;
                    $ct  = isset($cards_in[$key]['content']) ? wp_kses_post($cards_in[$key]['content']) : '';
                    $ct_cleaned = $this->clean_inline_styles($ct);
                    if ($ct !== $ct_cleaned) $styles_cleaned = true;
                    $cards_out[$key] = ['image' => max(0, $img), 'content' => $ct_cleaned];
                }

                $faqs_out = [];
                $partial_faqs = 0;
                if (isset($row['faqs']) && is_array($row['faqs'])) {
                    foreach ($row['faqs'] as $faq) {
                        $q = isset($faq['q']) ? trim(sanitize_text_field($faq['q'])) : '';
                        $a = isset($faq['a']) ? trim(wp_kses_post($faq['a'])) : '';
                        $a_cleaned = $this->clean_inline_styles($a);
                        if ($a !== $a_cleaned) $styles_cleaned = true;
                        
                        if ($q === '' && $a_cleaned === '') {
                            continue;
                        }
                        
                        $faqs_out[] = ['q' => $q, 'a' => $a_cleaned];
                        
                        if (($q === '' && $a_cleaned !== '') || ($q !== '' && $a_cleaned === '')) {
                            $partial_faqs++;
                        }
                    }
                }
                
                if ($partial_faqs > 0) {
                    add_settings_error(
                        self::PAGE_SLUG, 
                        'partial_faq', 
                        sprintf(
                            '%d FAQ(s) saved with incomplete data (missing question or answer). These will be saved but may not display properly on the frontend.', 
                            $partial_faqs
                        ), 
                        'warning'
                    );
                }

                $hasCards = !empty(array_filter($cards_out, function($c) {
                    return $c['image'] || trim($c['content']) !== '';
                }));
                $hasContent = ($s1_heading !== '' || trim($s1_content) !== '' || trim($s2_intro) !== '' || $hasCards || !empty($faqs_out));

                if ($region <= 0 && $category <= 0 && !$hasContent) continue;

                if ($region <= 0) {
                    add_settings_error(self::PAGE_SLUG, 'missing', 'Every row must have at least a Region selected. Category is optional.', 'error');
                    continue;
                }
                
                if (!get_term($region, self::REGION_TAX)) {
                    add_settings_error(self::PAGE_SLUG, 'invalid', 'Selected region no longer exists.', 'error');
                    continue;
                }
                
                if ($category > 0 && !get_term($category, self::CATEGORY_TAX)) {
                    add_settings_error(self::PAGE_SLUG, 'invalid', 'Selected category no longer exists.', 'error');
                    continue;
                }

                $key = $region . 'x' . $category;
                if (isset($seen[$key])) {
                    add_settings_error(self::PAGE_SLUG, 'dupe', 'Duplicate combination detected (Region × Category).', 'error');
                    continue;
                }
                $seen[$key] = true;

                $clean[] = [
                    'region'     => $region,
                    'category'   => $category,
                    's1_heading' => $s1_heading,
                    's1_content' => $s1_content,
                    's2_intro'   => $s2_intro, 
                    's2'         => $cards_out,
                    'faqs'       => $faqs_out,
                ];
            }
        }

        $message = sprintf('%d Combination(s) saved successfully.', count($clean));
        if ($force_clean_all) {
            $message .= ' 🧹 ALL content has been cleaned of white text and Google Docs formatting.';
        } elseif ($styles_cleaned) {
            $message .= ' Problematic inline styles (white text, Google Docs formatting) were automatically cleaned.';
        }
        add_settings_error(self::PAGE_SLUG, 'saved', $message, 'updated');
        return $clean;
    }

    public static function get_combo($region_term_id, $category_term_id) {
        $rows = get_option(self::OPTION_KEY, []);
        if (!is_array($rows) || empty($rows)) return null;
        foreach ($rows as $row) {
            if ((int)$row['region'] === (int)$region_term_id && (int)$row['category'] === (int)$category_term_id) {
                return $row;
            }
        }
        return null;
    }

    public static function render_sections($row, $print_schema = true) {
        if (!$row) return;

        if (!empty($row['s1_heading']) || !empty($row['s1_content'])) {
            echo '<section class="jl-s1">';
            if (!empty($row['s1_heading'])) echo '<h2>' . esc_html($row['s1_heading']) . '</h2>';
            if (!empty($row['s1_content'])) echo wpautop($row['s1_content']);
            echo '</section>';
        }

        if (!empty($row['s2']) && is_array($row['s2'])) {
            echo '<section class="jl-s2-cards"><div class="cards-wrap">';
        
            if (!empty($row['s2_intro'])) {
                echo '<div class="s2-intro">';
                echo wpautop($row['s2_intro']);
                echo '</div>';
            }
        
            echo '<div class="cards">';
            foreach (['coworking','day_offices','meeting_event','private_offices'] as $key) {
                $c = isset($row['s2'][$key]) ? $row['s2'][$key] : ['image'=>0,'content'=>''];
                echo '<article class="card">';
                if (!empty($c['image'])) echo wp_get_attachment_image((int)$c['image'], 'medium');
                if (!empty($c['content'])) echo wpautop($c['content']);
                echo '</article>';
            }
            echo '</div></section>';
        }

        if (!empty($row['faqs']) && is_array($row['faqs'])) {
            echo '<section class="jl-faq"><h3>FAQs</h3>';
            $entities = [];
            foreach ($row['faqs'] as $faq) {
                $q = isset($faq['q']) ? $faq['q'] : '';
                $a = isset($faq['a']) ? $faq['a'] : '';
                if ($q === '' && trim($a) === '') continue;
                echo '<article class="jl-faq-item">';
                if ($q !== '') echo '<h4>' . esc_html($q) . '</h4>';
                if ($a !== '') echo wpautop($a);
                echo '</article>';
                if ($q !== '' && $a !== '') {
                    $entities[] = [
                        "@type" => "Question",
                        "name"  => wp_strip_all_tags($q),
                        "acceptedAnswer" => [
                            "@type" => "Answer",
                            "text"  => wp_strip_all_tags($a),
                        ],
                    ];
                }
            }
            echo '</section>';
            if ($print_schema && !empty($entities)) {
                echo '<script type="application/ld+json">' . wp_json_encode(["@context"=>"https://schema.org","@type"=>"FAQPage","mainEntity"=>$entities], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . '</script>';
            }
        }
    }

    public static function the_sections_from_query($print_schema = true) {
        $region_slug = get_query_var(self::REGION_TAX) ?: get_query_var('job_listing_region');
        $cat_slug    = get_query_var(self::CATEGORY_TAX) ?: get_query_var('job_listing_category');
        $region = $region_slug ? get_term_by('slug', $region_slug, self::REGION_TAX) : null;
        $cat    = $cat_slug    ? get_term_by('slug', $cat_slug,    self::CATEGORY_TAX) : null;
        if ($region && $cat) {
            $row = self::get_combo($region->term_id, $cat->term_id);
            self::render_sections($row, $print_schema);
        }
    }
}

new JL_Archive_Combos_Plugin();
