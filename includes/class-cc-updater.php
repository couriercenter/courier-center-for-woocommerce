<?php
/**
 * Auto-updater — ελέγχει για νέες εκδόσεις στο GitHub
 * Repo: couriercenter/courier-center-for-woocommerce
 */
class CC_Updater {

    private $github_user = 'couriercenter';
    private $github_repo = 'courier-center-for-woocommerce';
    private $plugin_file;
    private $plugin_slug;

    public function __construct() {
        $this->plugin_file = CC_WC_PLUGIN_FILE;
        $this->plugin_slug = plugin_basename( CC_WC_PLUGIN_FILE );

        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'site_transient_update_plugins', array( $this, 'check_update' ) );
        add_action( 'upgrader_process_complete', array( $this, 'after_update' ), 10, 2 );
    }

    private function get_github_release() {
        $transient_key = 'cc_wc_github_release';
        $release = get_transient( $transient_key );

        if ( false === $release ) {
            $url = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
            $response = wp_remote_get( $url, array(
                'headers' => array( 'User-Agent' => 'WordPress/' . get_bloginfo('version') ),
                'timeout' => 10,
            ));

            if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
                return false;
            }

            $release = json_decode( wp_remote_retrieve_body( $response ) );
            set_transient( $transient_key, $release, 12 * HOUR_IN_SECONDS );
        }

        return $release;
    }

    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_github_release();
        if ( ! $release ) {
            return $transient;
        }

        $latest_version  = ltrim( $release->tag_name, 'v' );
        $current_version = $transient->checked[ $this->plugin_slug ] ?? CC_WC_VERSION;

        error_log( 'CC Updater - plugin_slug: ' . $this->plugin_slug );
        error_log( 'CC Updater - latest: ' . $latest_version . ' current: ' . $current_version );

        if ( version_compare( $current_version, $latest_version, '<' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) array(
                'id'           => $this->plugin_slug,
                'slug'         => dirname( $this->plugin_slug ),
                'plugin'       => $this->plugin_slug,
                'new_version'  => $latest_version,
                'url'          => "https://github.com/{$this->github_user}/{$this->github_repo}",
                'package'      => $release->zipball_url,
                'icons'        => array(),
                'banners'      => array(),
                'requires'     => '6.0',
                'tested'       => '6.9',
                'requires_php' => '7.4',
            );
        }

        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' || $args->slug !== dirname( $this->plugin_slug ) ) {
            return $result;
        }

        $release = $this->get_github_release();
        if ( ! $release ) {
            return $result;
        }

        return (object) array(
            'name'          => 'Courier Center for WooCommerce',
            'slug'          => dirname( $this->plugin_slug ),
            'version'       => ltrim( $release->tag_name, 'v' ),
            'author'        => 'Courier Center',
            'homepage'      => 'https://courier.gr',
            'download_link' => $release->zipball_url,
            'sections'      => array(
                'description' => 'Ενσωμάτωση Courier Center με WooCommerce.',
                'changelog'   => $release->body ?? '',
            ),
        );
    }

    public function after_update( $upgrader, $options ) {
        if ( $options['action'] === 'update' && $options['type'] === 'plugin' ) {
            delete_transient( 'cc_wc_github_release' );
        }
    }
}
