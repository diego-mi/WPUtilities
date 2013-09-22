<?php
/*
Plugin Name: WP Utilities Newsletter
Description: Newsletter
Version: 1.0
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

/* ----------------------------------------------------------
  Admin page & menus
---------------------------------------------------------- */

$wpunewsletteradmin_messages = array();


// Menu item
add_action( 'admin_menu', 'wpunewsletter_menu_page' );
function wpunewsletter_menu_page() {
    add_menu_page( 'Newsletter', 'Newsletter', 'manage_options', 'wpunewsletter', 'wpunewsletter_page' );
    add_submenu_page( 'wpunewsletter', 'Newsletter - Export', 'Export', 'manage_options', 'wpunewsletter-export', 'wpunewsletter_page_export' );
}

// Admin JS
add_action( 'admin_enqueue_scripts', 'wpunewsletter_enqueue_js' );
function wpunewsletter_enqueue_js( $hook ) {
    if ( isset( $_GET['page'] ) && $_GET['page'] == 'wpunewsletter' ) {
        wp_enqueue_script( 'wpunewsletter_js', plugin_dir_url( __FILE__ ) . 'assets/script.js' );
    }
}

// Admin page content
function wpunewsletter_page() {
    global $wpdb, $wpunewsletteradmin_messages;
    $table_name = $wpdb->prefix."wpunewsletter_subscribers";

    // Paginate
    $perpage = 50;
    $current_page = ( ( isset( $_GET['paged'] ) && is_numeric( $_GET['paged'] ) ) ? $_GET['paged']  : 1 );
    $nb_start = ( $current_page * $perpage ) - $perpage;
    $nb_results_req = $wpdb->get_row( "SELECT COUNT(id) as count_id FROM ".$table_name );
    $nb_results_total = (int) $nb_results_req->count_id;
    $max_page = ceil( $nb_results_total / $perpage );

    // Get page results
    $results = $wpdb->get_results( "SELECT * FROM " . $table_name . " ORDER BY id DESC LIMIT " . $nb_start . ", " . $perpage );
    $nb_results = count( $results );

    // Display wrapper
    echo '<div class="wrap"><div id="icon-options-general" class="icon32"></div><h2 class="title">Newsletter</h2>';

    if ( !empty( $wpunewsletteradmin_messages ) ) {
        echo '<p>'.implode( '<br />', $wpunewsletteradmin_messages ).'</p>';
    }

    echo '<h3>'.sprintf( __( 'Subscribers list : %s', 'wputh' ), $nb_results_total ).'</h3>';

    // If empty
    if ( $nb_results < 1 ) {
        // - Display blank slate message
        echo '<p>'.__( 'No subscriber for now.', 'wputh' ).'</p>';
    }
    else {
        echo '<form action="" method="post">';
        // - Display results
        echo '<table class="widefat">';
        $cols = '<tr><th><input type="checkbox" class="wpunewsletter_element_check" name="wpunewsletter_element_check" /></th><th>'.__( 'ID', 'wputh' ).'</th><th>'.__( 'Email', 'wputh' ).'</th><th>'.__( 'Date', 'wputh' ).'</th></tr>';
        echo '<thead>'.$cols.'</thead>';
        echo '<tfoot>'.$cols.'</tfoot>';
        foreach ( $results as $result ) {
            echo '<tbody><tr>
        <td style="width: 15px; text-align: right;"><input type="checkbox" class="wpunewsletter_element" name="wpunewsletter_element[]" value="'.$result->id.'" /></td>
        <td>'.$result->id.'</td>
        <td>'.$result->email.'</td>
        <td>'.$result->date_register.'</td>
        </tr></tbody>';
        }
        echo '</table>';
        echo wp_nonce_field( 'wpunewsletter_delete', 'wpunewsletter_delete_nonce' );
        echo '<p><button class="button-primary">'.__( 'Delete selected lines', 'wputh' ).'</button></p>';
        echo '</form>';
    }
    echo '</div>';

    if ( $max_page > 1 ) {
        $big = 999999999; // need an unlikely integer
        echo '<p>'.paginate_links( array(
                'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
                'format' => '/admin.php?page=wpunewsletter&paged=%#%',
                'current' => max( 1, $current_page ),
                'total' => $max_page
            ) ).'</p>';
    }
}

// Delete element
add_action( 'admin_init', 'wpunewsletter_delete_postaction' );
function wpunewsletter_delete_postaction() {
    global $wpdb, $wpunewsletteradmin_messages;
    $table_name = $wpdb->prefix."wpunewsletter_subscribers";
    $nb_delete = 0;
    if ( isset( $_POST['wpunewsletter_delete_nonce'] ) && wp_verify_nonce( $_POST['wpunewsletter_delete_nonce'], 'wpunewsletter_delete' ) && isset( $_POST['wpunewsletter_element'] ) && is_array( $_POST['wpunewsletter_element'] ) && !empty( $_POST['wpunewsletter_element'] ) ) {
        foreach ( $_POST['wpunewsletter_element'] as $id ) {
            $wpdb->delete( $table_name, array( 'id' => $id ) );
            $nb_delete++;
        }
    }

    if ( $nb_delete > 0 ) {
        $wpunewsletteradmin_messages[] = 'Mail suppressions : '.$nb_delete;
    }
}

// Admin Page - Export
function wpunewsletter_page_export() {
    echo '<div class="wrap">
    <div id="icon-options-general" class="icon32"></div><h2 class="title">Newsletter - Export</h2>
    <form action="" method="post"><p>';
    echo wp_nonce_field( 'wpunewsletter_export', 'wpunewsletter_export_nonce' );
    echo '<button type="submit" class="button-primary">'.__( 'Export addresses', 'wputh' ).'</button>';
    echo '</p></form>
    </div>';
}

add_action( 'admin_init', 'wpunewsletter_export_postaction' );
// Generate CSV for export
function wpunewsletter_export_postaction() {
    global $wpdb;
    $table_name = $wpdb->prefix."wpunewsletter_subscribers";
    $file_name = sanitize_title( get_bloginfo( 'name' ) ) . '-' . date( 'Y-m-d' ) .'-wpunewsletter'.'.csv';
    // Check if export is correctly asked
    if ( isset( $_POST['wpunewsletter_export_nonce'] ) && wp_verify_nonce( $_POST['wpunewsletter_export_nonce'], 'wpunewsletter_export' ) ) {
        $handle = @fopen( 'php://output', 'w' );
        $results = $wpdb->get_results( "SELECT * FROM ".$table_name, ARRAY_N );

        // Send CSV Headers
        header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
        header( 'Content-Description: File Transfer' );
        header( "Content-type: text/csv" );
        header( "Content-Disposition: attachment; filename=".$file_name );
        header( "Expires: 0" );
        header( "Pragma: public" );

        // Export as CSV lines
        foreach ( $results as $data ) {
            fputcsv( $handle, $data );
        }

        // Send CSV File
        fclose( $handle );
        exit;
    }
}

/* ----------------------------------------------------------
  Widget
---------------------------------------------------------- */

$wpunewsletter_messages = array();

// Create widget Form
add_action( 'widgets_init', 'wpunewsletter_form_register_widgets' );
function wpunewsletter_form_register_widgets() {
    register_widget( 'wpunewsletter_form' );
}
class wpunewsletter_form extends WP_Widget {
    function wpunewsletter_form() {parent::WP_Widget( false,
            '[WPU] Newsletter Form',
            array( 'description' => 'Newsletter Form' )
        );}
    function form( $instance ) {}
    function update( $new_instance, $old_instance ) {return $new_instance;}
    function widget( $args, $instance ) {
        global $wpunewsletter_messages;
        echo $args['before_widget'];
        if ( !empty( $wpunewsletter_messages ) ) {
            echo '<p>'.implode( '<br />', $wpunewsletter_messages ).'</p>';
        } ?>
        <form action="" method="post">
            <div>
                <label for="wpunewsletter_email"><?php echo __( 'Email', 'wputh' ); ?></label>
                <input type="email" name="wpunewsletter_email" id="wpunewsletter_email" value="" required />
                <button type="submit" class="cssc-button"><?php echo __( 'Register', 'wputh' ); ?></button>
            </div>
        </form>
        <?php echo $args['after_widget'];
    }
}

// Widget POST Action
add_action( 'init', 'wpunewsletter_postaction' );
function wpunewsletter_postaction() {
    global $wpunewsletter_messages, $wpdb;
    $table_name = $wpdb->prefix."wpunewsletter_subscribers";
    // If there is a valid email address
    if ( isset( $_POST['wpunewsletter_email'] ) && filter_var( $_POST['wpunewsletter_email'] , FILTER_VALIDATE_EMAIL ) ) {
        // Is it already in our base ?
        $testbase = $wpdb->get_row( $wpdb->prepare( 'SELECT email FROM '.$table_name.' WHERE email = %s', $_POST['wpunewsletter_email'] ) );
        if ( isset( $testbase->email ) ) {
            $wpunewsletter_messages[] = __( 'This mail is already registered', 'wputh' );
        }
        else {
            $insert = $wpdb->insert(
                $table_name,
                array(
                    'email' => $_POST['wpunewsletter_email'],
                )
            );
            if ( $insert === false ) {
                $wpunewsletter_messages[] = __( "This mail can't be registered", 'wputh' );
            }
            else {
                $wpunewsletter_messages[] = __( 'This mail is now registered', 'wputh' );
            }
        }
    }

    if ( isset( $_POST['ajax'] ) ) {
        if ( !empty( $wpunewsletter_messages ) ) {
            echo '<p>'.implode( '<br />', $wpunewsletter_messages ).'</p>';
        }
        die;
    }
}

/* ----------------------------------------------------------
  Hooks Install
---------------------------------------------------------- */

register_activation_hook( __FILE__, 'wpunewsletter_activate' );
function wpunewsletter_activate() {
    global $wpdb;
    // Create or update database
    $table_name = $wpdb->prefix."wpunewsletter_subscribers";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( "CREATE TABLE ".$table_name." (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `email` varchar(100) DEFAULT NULL,
        `date_register` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `is_valid` tinyint(1) unsigned DEFAULT '0' NOT NULL,
        PRIMARY KEY (`id`)
    );" );
}