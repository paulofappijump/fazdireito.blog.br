<?php

class Wpil_Rest
{
    const NAMESPACE = 'link-whisper';

    const ROUTE     = 'code';

    public function register ()
    {
        $this->register_rest();
    }

    public function register_rest ()
    {
        add_action('rest_api_init', function ( $wp_rest_server )
        {
            /**
             * @var WP_REST_Server $wp_rest_server
             */

            register_rest_route(self::NAMESPACE, self::ROUTE, [
                'methods'             => 'POST',
                'callback'            => [
                    $this,
                    'handler_rest'
                ],
                'permission_callback' => "__return_true",
                'show_in_index'       => false
            ]);

        });
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return string|\WP_Error
     */
    public function handler_rest ( WP_REST_Request $request )
    {
        if ( !empty($request->get_param('code')) ) {
            $code     = $request->get_param('code');
            $response = Wpil_SearchConsole::get_access_token(trim($code));

            $message = [
                'status' => $response['access_valid'],
                'text'   => $response['message']
            ];

            set_transient('wpil_gsc_access_status_message', $message, 20);

            if ( !empty($response['access_valid']) ) {
                update_option('wpil_gsc_app_authorized', true);
            }

            return 'ok';
        } elseif ( !empty($request->get_param('error')) ) {
            $message = [
                'status' => false,
                'text'   => __('Access denied', 'rank-logic')
            ];

            set_transient('wpil_gsc_access_status_message', $message, 20);
        }

        return new WP_Error(400, 'Bad request', [ 'status' => 404 ]);
    }
}
