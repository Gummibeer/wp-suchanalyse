<?php
/**
 * Plugin Name: Wordpress Suchanalayse
 * Plugin URI: https://github.com/Gummibeer/wp-suchanalyse
 * Description: Speichert seiteninterne Suchanfragen
 * Version: 1.1.5
 * Text Domain: wp_suchanalyse
 * Author: Tom Witkowski
 * Author URI: https://github.com/Gummibeer
 * Copyright 2014 Tom Witkowski (email : dev.gummibeer@gmail.com)
 * License: GPL2
 */

class wp_suchanalyse {
    private $wp_basepath;
    private $plugin_file;
    private $plugin_dir;
    private $plugin_url;
    private $plugin_name;
    private $plugin_slug;
    private $plugin_version;
    private $table_name;
    private $table_create;

    public function __construct() {
        global $wpdb;

        $this->plugin_name = 'Suchanalyse';
        $this->plugin_slug = 'wp_suchanalyse';
        $this->plugin_version = '1.1.5';

        $this->wp_basepath = ABSPATH;
        $this->plugin_file = __FILE__;
        $this->plugin_dir = dirname($this->plugin_file).'/';
        $this->plugin_url = plugins_url().'/'.$this->plugin_slug.'/';

        $this->table_name = $wpdb->prefix . $this->plugin_slug . '_suchanfragen';
        $this->table_create =   "CREATE TABLE $this->table_name (
                                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                                    keyword VARCHAR(55) DEFAULT '' NOT NULL,
                                    count mediumint(9) NOT NULL,
                                    PRIMARY KEY (id),
                                    UNIQUE KEY keyword (keyword)
                                );";

        require_once( $this->wp_basepath . 'wp-admin/includes/upgrade.php' );
        register_activation_hook( __FILE__, array( $this, 'initial_install' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );

        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );

        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_menu', array( $this, 'add_options_page' ) );

        add_action( 'template_redirect', array( $this, 'register_search_query' ) );

        $this->do_get_var();
    }

    public function initial_install() {
        dbDelta( $this->table_create );
    }

    public function load_scripts() {
        wp_register_script('google-chart-api', 'https://www.google.com/jsapi');
        wp_enqueue_script('google-chart-api');
        wp_register_style('wp-suchanalyse-font', plugins_url() . '/wp_suchanalyse/css/whhg.css');
        wp_enqueue_style('wp-suchanalyse-font');
        wp_register_style('wp-suchanalyse-css', plugins_url() . '/wp_suchanalyse/css/styles.css');
        wp_enqueue_style('wp-suchanalyse-css');
    }

    public function register_search_query() {
        global $wpdb;
        $search_query = get_search_query(false);

        $search_query = preg_replace('/<script(.*?)>(.*?)<\/script>/i', ' ', $search_query);
        $search_query = esc_attr($search_query);
        $search_query = preg_replace('/&(?:[a-z\d]+|#\d+|#x[a-f\d]+);/i', ' ', $search_query);
        $search_query = preg_replace('/[^A-Za-z0-9äöüß]/', ' ', $search_query);
        $search_query = preg_replace('/\s\s+/', ' ', $search_query);
        $search_query = trim($search_query);
        $search_query = strtolower($search_query);

        $search_keywords = explode(' ', $search_query);
        foreach($search_keywords as $keyword) {
            $sql = strval( 'SELECT * FROM '.$this->table_name.' WHERE keyword = "'.$keyword.'"' );
            $result = $wpdb->get_row( $sql );
            if($result->count == 0) {
                $wpdb->insert( $this->table_name, array( 'keyword' => $keyword, 'count' => '1' ) );
            } else {
                $wpdb->update( $this->table_name, array( 'count' => strval($result->count * 1 + 1) ), array( 'keyword' => $keyword ) );
            }
        }

        if($search_query != '') {
            $sql = strval( 'SELECT * FROM '.$this->table_name.' WHERE keyword = "('.$search_query.')"' );
            $result = $wpdb->get_row( $sql );
            if($result->count == 0) {
                $wpdb->insert( $this->table_name, array( 'keyword' => '('.$search_query.')', 'count' => '1' ) );
            } else {
                $wpdb->update( $this->table_name, array( 'count' => strval($result->count * 1 + 1) ), array( 'keyword' => '('.$search_query.')' ) );
            }
        }
    }



    public function add_dashboard_widget() {
        wp_add_dashboard_widget($this->plugin_slug.'_dashboard_widget', 'interne Suchanalyse', array( $this, 'display_dashboard_widget' ));
    }

    public function display_dashboard_widget() {
        global $wpdb;
        $sql = strval( 'SELECT id FROM '.$this->table_name.' ORDER BY count DESC' );
        $results = $wpdb->get_col( $sql );

        $search_count = 0;
        $no_search_count = 0;

        $out = '';

        $out .= '<h4>einzelne Such-Keywords</h4>';
        $out .= '<ul>';
        foreach($results as $id) {
            $sql = strval( 'SELECT * FROM '.$this->table_name.' WHERE id = "'.$id.'"' );
            $result = $wpdb->get_row( $sql );
            if($result->keyword != '' && preg_match('/\((.*)\)/', $result->keyword) != 1) {
                if( !in_array( $result->keyword, $this->explode_keywords( get_option($this->plugin_slug.'_blocked_keywords') ) ) ) {
                    $search_check =& new WP_Query("s=$result->keyword & showposts=-1");
                    $search_results = $search_check->post_count;
                    $search_class = $search_results == 0 ? ' no_post' : '';

                    $out .= '<li>'.
                            '<strong>'.$result->keyword.'</strong>'.
                            '<span class="counter">('.$result->count.')</span>'.
                            '<span class="results'.$search_class.'">('.$search_results.')</span>'.
                            '<a href="'.add_query_arg( array( $this->plugin_slug => 'block', 'keyword' => $result->keyword ) ).'" title="Suchwort blockieren" class="block"><i class="icon-security-shield"></i></a>'.
                            '</li>';
                }
            } elseif($result->keyword == '' && preg_match('/\((.*)\)/', $result->keyword) != 1) {
                $no_search_count = $no_search_count + $result->count;
            }
        }
        $out .= '</ul>';
        $out .= '<div class="clear"></div>';

        $out .= '<hr />';
        $out .= '<br />';
        $out .= '<h4>gesamte Suchanfragen</h4>';
        $out .= '<ul>';
        foreach($results as $id) {
            $sql = strval( 'SELECT * FROM '.$this->table_name.' WHERE id = "'.$id.'"' );
            $result = $wpdb->get_row( $sql );
            if($result->keyword != '' && preg_match('/\((.*)\)/', $result->keyword) == 1) {
                $result->keyword = str_replace(array('(', ')'), '', $result->keyword);
                $i = count( explode( ' ', $result->keyword ) );
                $k = 0;
                foreach($this->explode_keywords( get_option($this->plugin_slug.'_blocked_keywords') ) as $blocked) {
                    $k = $k + preg_match( '/ ('.$blocked.') /i', ' '.$result->keyword.' ' );
                    $result->keyword = preg_replace( '/ ('.$blocked.') /i', ' <strike>'.$blocked.'</strike> ', ' '.$result->keyword.' ' );
                    $result->keyword = trim($result->keyword);
                }

                if( $i > $k ) {
                    $out .= '<li>'.
                            '<strong>'.$result->keyword.'</strong>'.
                            '<span class="counter">('.$result->count.')</span>'.
                            '<a href="'.add_query_arg( array( $this->plugin_slug => 'delete', 'id' => $result->id ) ).'" title="Suchanfrage löschen" class="delete"><i class="icon-circledelete"></i></a>'.
                            '</li>';
                }

                $search_count = $search_count + $result->count;
            }
        }
        $out .= '</ul>';
        $out .= '<div class="clear"></div>';

        $out .= '<hr />';
        $out .= '<br />';
        $out .= '<h4>Suchanfragenanzahl <small>'.$search_count.' Suchanfragen / '.($search_count + $no_search_count).' Seitenaufrufe</small></h4>';
        $out .= '<div id="'.$this->plugin_slug.'_chart"></div>';
        $out .=		"<script type=\"text/javascript\">
						jQuery(window).on('load', function() {
							google.load('visualization', '1.0', {'packages':['corechart'], 'callback':drawChart});
							function drawChart() {
								var data = new google.visualization.DataTable();
									data.addColumn('string', 'Typ');
									data.addColumn('number', 'Anzahl');
									data.addRows([
										['Suchanfragen', ".$search_count."],
										['ohne Suchanfrage', ".$no_search_count."]
									]);

								var options = {'title':'Verhältnis von Seitenaufrufen zu Suchanfragen',
								                'colors':['#13a89e', '#3f4953'],
												'width':380,
												'height':260};
								var chart = new google.visualization.PieChart(document.getElementById('".$this->plugin_slug."_chart'));
								chart.draw(data, options);
							}
						});
					</script>";

        $out .= '<br />';
        $out .= '<hr />';
        $out .= '<h4>Beispiel</h4>';
        $out .= '<ul>';
        $out .= '<li>';
        $out .= '<strong>Suchwort</strong>';
        $out .= '<span class="counter">Suchanzahl</span>';
        $out .= '<span class="results">Suchergebnisse</span>';
        $out .= '<a href="#" title="Suchwort blockieren" class="block">Aktion</a>';
        $out .= '</li>';
        $out .= '</ul>';
        $out .= '<div class="clear"></div>';

        $out .= '<hr />';
        $out .= '<a href="'.add_query_arg( array( $this->plugin_slug => 'delete' ) ).'" title="">alle Daten löschen</a>';

        echo $out;
    }



    public function register_settings() {
        register_setting( $this->plugin_slug.'_settings_group', $this->plugin_slug.'_blocked_keywords' );
    }

    public function add_options_page() {
        add_menu_page( 'Suchanalyse', 'Suchanalyse', 'administrator', $this->plugin_slug.'_options_page', array( $this, 'display_options_page' ) );
    }

    public function display_options_page() {
        $keywords = $this->explode_keywords( get_option($this->plugin_slug.'_blocked_keywords') );
        $keywords = array_map('strtolower', $keywords);
        $keywords = array_unique( $keywords );
        sort($keywords);
        update_option( $this->plugin_slug.'_blocked_keywords', $this->implode_keywords($keywords) );
?>
        <h2>Suchanalyse</h2>
        <div class="postbox-container" id="<?php echo $this->plugin_slug; ?>_options_page" style="width:100%;">
            <div class="metabox-holder">
                <div class="meta-box-sortables ui-sortable">
                    <div class="postbox">
                        <h3 class="hndle">
                            <span>Einstellungen</span>
                        </h3>
                        <div class="inside">
                            <?php
                            if($_GET['settings-updated']) {
                                echo $this->return_admin_notice( '<strong>Erfolg:</strong> Änderungen erfolgreich übernommen.', 'updated' );
                            }
                            ?>

                            <form action="options.php" method="post" name="options">
                                <?php
                                settings_fields( $this->plugin_slug.'_settings_group' );
                                do_settings_sections( $this->plugin_slug.'_settings_group' );
                                ?>

                                <fieldset>
                                    <div class="row">
                                        <div class="col col-3">
                                            <label for="<?php echo $this->plugin_slug.'_blocked_keywords'; ?>">blockierte Suchwörter</label>
                                        </div>
                                        <div class="col col-9">
                                            <textarea name="<?php echo $this->plugin_slug.'_blocked_keywords'; ?>" id="<?php echo $this->plugin_slug.'_blocked_keywords'; ?>"><?php echo get_option($this->plugin_slug.'_blocked_keywords'); ?></textarea>
                                            zu blockierende Suchwörter einfach per Leerzeichen, Komma oder Zeilenumbruch trennen.
                                        </div>
                                    </div>
                                </fieldset>

                                <p class="submit">
                                    <input id="submit" class="button button-primary" type="submit" value="Einstellungen speichern" name="submit" />
                                </p>
                            </form>
<?php

        global $wpdb;
        $sql = strval( 'SELECT id FROM '.$this->table_name.' ORDER BY count DESC' );
        $results = $wpdb->get_col( $sql );

        $search_count = 0;
        $no_search_count = 0;

        $out = '<h4>einzelne Such-Keywords</h4>';
        $out .= '<ul>';
        foreach($results as $id) {
            $sql = strval( 'SELECT * FROM '.$this->table_name.' WHERE id = "'.$id.'"' );
            $result = $wpdb->get_row( $sql );
            if($result->keyword != '' && preg_match('/\((.*)\)/', $result->keyword) != 1) {
                if( !in_array( $result->keyword, $this->explode_keywords( get_option($this->plugin_slug.'_blocked_keywords') ) ) ) {
                    $search_check =& new WP_Query("s=$result->keyword & showposts=-1");
                    $search_results = $search_check->post_count;
                    $search_class = $search_results == 0 ? ' no_post' : '';

                    $out .= '<li>'.
                            '<strong>'.$result->keyword.'</strong>'.
                            '<span class="counter">('.$result->count.')</span>'.
                            '<span class="results'.$search_class.'">('.$search_results.')</span>'.
                            '<a href="'.add_query_arg( array( $this->plugin_slug => 'block', 'keyword' => $result->keyword ) ).'" title="Suchwort blockieren" class="block"><i class="icon-security-shield"></i></a>'.
                            '</li>';
                }
            } elseif($result->keyword == '' && preg_match('/\((.*)\)/', $result->keyword) != 1) {
                $no_search_count = $no_search_count + $result->count;
            }
        }
        $out .= '</ul>';
        $out .= '<div class="clear"></div>';

        $out .= '<hr />';
        $out .= '<br />';
        $out .= '<h4>gesamte Suchanfragen</h4>';
        $out .= '<ul>';
        foreach($results as $id) {
            $sql = strval( 'SELECT * FROM '.$this->table_name.' WHERE id = "'.$id.'"' );
            $result = $wpdb->get_row( $sql );
            if($result->keyword != '' && preg_match('/\((.*)\)/', $result->keyword) == 1) {
                $result->keyword = str_replace(array('(', ')'), '', $result->keyword);
                foreach($this->explode_keywords( get_option($this->plugin_slug.'_blocked_keywords') ) as $blocked) {
                    $result->keyword = preg_replace( '/ ('.$blocked.') /i', ' <strike>'.$blocked.'</strike> ', ' '.$result->keyword.' ' );
                    $result->keyword = trim($result->keyword);
                }

                $out .= '<li>'.
                        '<strong>'.$result->keyword.'</strong>'.
                        '<span class="counter">('.$result->count.')</span>'.
                        '<a href="'.add_query_arg( array( $this->plugin_slug => 'delete', 'id' => $result->id ) ).'" title="Suchanfrage löschen" class="delete"><i class="icon-circledelete"></i></a>'.
                        '</li>';
                $search_count = $search_count + $result->count;
            }
        }
        $out .= '</ul>';
        $out .= '<div class="clear"></div>';

        $out .= '<hr />';
        $out .= '<br />';
        $out .= '<h4>Suchanfragenanzahl <small>'.$search_count.' Suchanfragen / '.($search_count + $no_search_count).' Seitenaufrufe</small></h4>';
        $out .= '<div id="'.$this->plugin_slug.'_chart"></div>';
        $out .=		"<script type=\"text/javascript\">
						jQuery(window).on('load', function() {
							google.load('visualization', '1.0', {'packages':['corechart'], 'callback':drawChart});
							function drawChart() {
								var data = new google.visualization.DataTable();
									data.addColumn('string', 'Typ');
									data.addColumn('number', 'Anzahl');
									data.addRows([
										['Suchanfragen', ".$search_count."],
										['ohne Suchanfrage', ".$no_search_count."]
									]);

								var options = {'title':'Verhältnis von Seitenaufrufen zu Suchanfragen',
								                'colors':['#13a89e', '#3f4953'],
												'width':380,
												'height':260};
								var chart = new google.visualization.PieChart(document.getElementById('".$this->plugin_slug."_chart'));
								chart.draw(data, options);
							}
						});
					</script>";

        $out .= '<br />';
        $out .= '<hr />';
        $out .= '<h4>Beispiel</h4>';
        $out .= '<ul>';
        $out .= '<li>';
        $out .= '<strong>Suchwort</strong>';
        $out .= '<span class="counter">Suchanzahl</span>';
        $out .= '<span class="results">Suchergebnisse</span>';
        $out .= '<a href="#" title="Suchwort blockieren" class="block">Aktion</a>';
        $out .= '</li>';
        $out .= '</ul>';
        $out .= '<div class="clear"></div>';

        $out .= '<hr />';
        $out .= '<a href="'.add_query_arg( array( $this->plugin_slug => 'delete' ) ).'" title="">alle Daten löschen</a>';

        echo $out;
        ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php
    }



    private function do_get_var() {
        if( $_GET[$this->plugin_slug] == 'delete' ) {
            $id = $_GET['id'] ? esc_sql( esc_attr( $_GET['id'] ) ) * 1 : false;
            if( is_numeric($id) ) {
                $status = $this->delete_single_data( $id );
            } else {
                $status = $this->delete_table_data();
            }

            switch($status) {
                case true:
                    echo $this->return_admin_notice( '<strong>Suchanalayse</strong>: Daten wurden erfolgreich aus der Datenbank gelöscht.', 'updated' );
                    break;
                case false:
                    echo $this->return_admin_notice( '<strong>Suchanalayse</strong>: Es ist ein Fehler aufgetreten - Daten konnten nicht gelöscht werden.', 'error' );
                    break;
            }
        } elseif( $_GET[$this->plugin_slug] == 'block' && !empty( $_GET['keyword'] ) ) {
            $this->add_keyword( $_GET['keyword'] );
        }
    }

    private function return_admin_notice( $text = '', $class = 'updated' ) {
        $out = '';
        $out .= '<div class="'.$class.'"><p>';
        $out .= $text;
        $out .= '</p></div>';
        return $out;
    }

    private function delete_table_data() {
        global $wpdb;
        return $wpdb->query( 'TRUNCATE TABLE '.$this->table_name );
    }

    private function delete_single_data( $id ) {
        global $wpdb;

        $sql = strval( 'SELECT * FROM '.$this->table_name.' WHERE id = "'.$id.'"' );
        $result = $wpdb->get_row( $sql );
        if($result->keyword != '' && preg_match('/\((.*)\)/', $result->keyword) == 1) {
            $result->keyword = str_replace(array('(', ')'), '', $result->keyword);
            foreach($this->explode_keywords( $result->keyword ) as $keyword) {

                $sql = strval( 'SELECT * FROM '.$this->table_name.' WHERE keyword = "'.$keyword.'"' );
                $single = $wpdb->get_row( $sql );

                if( $single->count <= $result->count ) {
                    $wpdb->delete( $this->table_name, array( 'id' => $single->id ), array( '%d' ) );
                } else {
                    $wpdb->update( $this->table_name, array( 'count' => strval($single->count * 1 - $result->count) ), array( 'id' => $single->id ) );
                }
            }
        }

        return $wpdb->delete( $this->table_name, array( 'id' => $id ), array( '%d' ) );
    }

    private function add_keyword( $keyword ) {
        $keyword = esc_sql( esc_attr( $keyword ) );
        $keyword = trim( $keyword );
        $keywords = $this->explode_keywords( get_option($this->plugin_slug.'_blocked_keywords') );
        array_push( $keywords, $keyword );
        update_option( $this->plugin_slug.'_blocked_keywords', $this->implode_keywords($keywords) );
    }

    private function explode_keywords( $keywords ) {
        $keywords = trim( $keywords );
        $keywords = str_replace( ',', ' ', $keywords );
        $keywords = preg_replace('/[\t\n]/i', ' ', $keywords);
        $keywords = preg_replace('/[\s]{2,}/i', ' ', $keywords);
        $keywords = explode( ' ', $keywords );
        return $keywords;
    }

    private function implode_keywords( $keywords ) {
        $keywords = implode( ', ', $keywords );
        $keywords = trim( $keywords, ', ' );
        $keywords = trim( $keywords );
        return $keywords;
    }
}

$wp_suchanalyse = new wp_suchanalyse;