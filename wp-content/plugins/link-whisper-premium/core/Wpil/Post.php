<?php

/**
 * Work with post
 */
class Wpil_Post
{
    public static $advanced_custom_fields_list = null;
    public static $post_types_without_editors = array(
        'web-story'
    );

    /**
     * Register services
     */
    public function register()
    {
        add_filter('wp_insert_post_data', [$this, 'addLinksToContent'], 9999, 3);
        add_action('wp_ajax_wpil_editor_reload', [$this, 'editorReload']);
        add_action('wp_ajax_wpil_is_outbound_links_added', [$this, 'isOutboundLinksAdded']);
        add_action('wp_ajax_wpil_is_inbound_links_added', [$this, 'isInboundLinksAdded']);
        add_action('wp_ajax_wpil_ignore_orphaned_post', [$this, 'ajaxIgnoreOrphanedPost']);
        add_action('draft_to_published', [$this, 'saveAutolinksToPost'], 998);
        add_action('save_post', [$this, 'saveAutolinksToPost'], 998);
        add_action('draft_to_published', [$this, 'updateStatMark'], 99999);
        add_action('save_post', [$this, 'updateStatMark'], 99999);
        add_action('before_delete_post', [$this, 'deleteReferences']);
        add_action('save_post', [$this, 'addLinkToMetaContent'], 9999, 1);
        add_filter('wp_link_query_args', array(__CLASS__, 'filter_custom_link_post_types'), 10, 1);
        add_filter('wp_link_query', array(__CLASS__, 'custom_link_category_search'), 10, 2);
        add_filter('et_fb_ajax_save_verification_result', array(__CLASS__, 'verify_divi_save_status'));
    }

    /**
     * Add links to content before post update
     */
    public static function addLinksToContent($data, $post, $unsanitized_postarr)
    {
        //get links from DB
        $meta = get_post_meta($post['ID'], 'wpil_links', true);

        if (is_null($data)) {
            $data = get_post($post['ID'], ARRAY_A);
            $data['post_content'] = addslashes($data['post_content']);
            $data_null = true;
        }

        // if this is a Google Web Story
        if(self::get_post_processing_type($data) === 'web-story'){ // todo: implement a pre-insert content processor to handle this kind of thing! Also, create a post-insert handler.
            // filter the post data so we retain the full post as it's stored in the database
            $data = self::use_unsanitized_content($data, $unsanitized_postarr);
        }

        // if there are links to insert
        if (!empty($meta) && !empty($data)) {

            // if we have the last modified date and the user doesn't want the post modified date to change
            if(!empty($post) && isset($post['post_modified']) && isset($post['post_modified_gmt']) && !Wpil_Settings::updatePostModifiedDate()){
                // set the current modified date for it
                $data['post_modified'] = $post['post_modified'];
                $data['post_modified_gmt'] = $post['post_modified_gmt'];
            }

            //update post text
            foreach ($meta as $link) {
                $changed_sentence = self::getSentenceWithAnchor($link);
                $link['sentence'] = Wpil_Word::removeQuotes($link['sentence']);

                if (strpos($data['post_content'], $link['sentence']) === false) {
                    $sentence = addslashes($link['sentence']);
                } else {
                    $sentence = $link['sentence'];
                }

                $force_insert = (isset($link['keyword_data']) && !empty($link['keyword_data']->force_insert)) ? true: false;

                Wpil_Editor_Kadence::insertLink($data['post_content'], $sentence, $changed_sentence);

                if (strpos($data['post_content'], $sentence) !== false) {
                    $changed_sentence = self::changeByACF($data['post_content'], $link['sentence'], $changed_sentence);
                    self::insertLink($data['post_content'], $sentence, $changed_sentence, $force_insert);
                }

                // if this is a WooCommerce product, and the link is for the excerpt/short description & isn't possible for the main content
                if( strpos($data['post_content'], $sentence) === false && 
                    strpos($data['post_excerpt'], $sentence) !== false && 
                    defined('WC_PLUGIN_FILE') && 
                    'product' === $data['post_type'] && 
                    in_array('product', Wpil_Settings::getPostTypes()))
                {
                    // add the link to the excerpt
                    self::insertLink($data['post_excerpt'], $sentence, $changed_sentence, $force_insert);
                }

                // if the Enfold Advanced editor is active
                if( isset($post['aviaLayoutBuilder_active']) && 'active' === $post['aviaLayoutBuilder_active'] && 
                    isset($post['_aviaLayoutBuilderCleanData']) && !empty($post['_aviaLayoutBuilderCleanData']))
                {
                    // add links to the submitted form content
                    if (strpos($_POST['_aviaLayoutBuilderCleanData'], $sentence) !== false) {
                        self::insertLink($_POST['_aviaLayoutBuilderCleanData'], $sentence, $changed_sentence, $force_insert);
                    }
                }
            }

            // if the post doesn't belong to a post type that an editor doesn't apply to
            if(!in_array(self::get_post_processing_type($data), self::$post_types_without_editors, true)){
                self::editors('addLinks', [$meta, $post['ID'], &$data['post_content']]);
            }

            // if this is a Google Web Story
            if(self::get_post_processing_type($data) === 'web-story'){
                // filter the post data so we retain the full post as it's stored in the database
                $data = self::filter_webstory_content($data, !empty($unsanitized_postarr));
            }

            if (!empty($data_null)) {
                Wpil_Word::addSlashesToNewLine($data['post_content']);

                $update = [
                    'ID' => $post['ID'],
                    'post_content' => $data['post_content'],
                    'post_excerpt' => $data['post_excerpt']
                ];

                if(isset($data['post_content_filtered'])){
                    $update['post_content_filtered'] = $data['post_content_filtered'];
                }

                wp_update_post($update);

                // remove any ghost links
                $new_post = new Wpil_Model_Post($post['ID']);
                Wpil_Keyword::deleteGhostLinks($new_post);
            }

            if (WPIL_STATUS_LINK_TABLE_EXISTS){
                Wpil_Report::update_post_in_link_table($post['ID']);
            }
        }elseif(self::get_post_processing_type($data) === 'web-story'){
            // if there are no links to insert and this is a Google Web Story
            // filter the post data so we retain the full post as it's stored in the database
            $data = self::filter_webstory_content($data, !empty($unsanitized_postarr));
        }

        //return updated post data
        return $data;
    }

    /**
     * Replaces the content of the post object with the unsanitized version
     **/
    public static function use_unsanitized_content($data = array(), $unsanitized_postarr = array()){
		if(isset($unsanitized_postarr['post_content_filtered'])){
			$data['post_content_filtered'] = $unsanitized_postarr['post_content_filtered'];
		}

		if(isset($unsanitized_postarr['post_content'])){
			$data['post_content'] = $unsanitized_postarr['post_content'];
		}

		return $data;
    }

    public static function filter_webstory_content($data = array(), $using_unsanitized = false){
        if(empty($data)){
            return $data;
        }

        return self::filter_insert_post_data($data, $using_unsanitized);
    }

    /**
     * Google Web Story content filtering functionality borrowed from the Web Stories plugin version 1.22.1
     * Including license from the time of borrowing, and modifying as allowed by license.
     * As always, the license applies to the utilized code and is not a claim that Link Whisper is licensed with this license.
     **/

    /**
     * Copyright 2020 Google LLC
     *
     * Licensed under the Apache License, Version 2.0 (the "License");
     * you may not use this file except in compliance with the License.
     * You may obtain a copy of the License at
     *
     *     https://www.apache.org/licenses/LICENSE-2.0
     *
     * Unless required by applicable law or agreed to in writing, software
     * distributed under the License is distributed on an "AS IS" BASIS,
     * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
     * See the License for the specific language governing permissions and
     * limitations under the License.
     */

	/**
	 * Filters slashed post data just before it is inserted into the database.
	 *
	 * Used to run story HTML markup through KSES on our own, but with some filters applied
	 * that should only affect the web-story post type.
	 *
	 * This allows storing full AMP HTML documents in post_content for stories, which require
	 * more allowed HTML tags and a patched version of {@see safecss_filter_attr}.
	 *
	 * @since 1.8.0
	 *
	 * @param array|mixed $data                An array of slashed, sanitized, and processed post data.
	 * @param array       $postarr             An array of sanitized (and slashed) but otherwise unmodified post data.
	 * @param array       $unsanitized_postarr An array of slashed yet *unsanitized* and unprocessed post data as
	 *                                         originally passed to wp_insert_post().
	 * @return array|mixed Filtered post data.
	 */
	public static function filter_insert_post_data($data, $using_unsanitized){
		if(!\is_array($data)){
			return $data;
		}

        // filter the post content filtered to apply formatting
		if(isset($data['post_content_filtered'])){
			$data['post_content_filtered'] = self::filter_story_data($data['post_content_filtered'], $using_unsanitized);
		}

        if(current_user_can('unfiltered_html')){
            return $data;
        }

		if(isset($data['post_content'])){
			add_filter('safe_style_css', [__CLASS__, 'filter_safe_style_css']);
			add_filter('wp_kses_allowed_html', [__CLASS__, 'filter_kses_allowed_html'], 10, 2);

			$data['post_content'] = self::filter_content_save_pre_before_kses($data['post_content']);

			$data['post_content'] = wp_filter_post_kses($data['post_content']);
			$data['post_content'] = self::filter_content_save_pre_after_kses($data['post_content']);

			remove_filter('safe_style_css', [__CLASS__, 'filter_safe_style_css']);
			remove_filter('wp_kses_allowed_html', [__CLASS__, 'filter_kses_allowed_html']);
		}

		return $data;
	}

	/**
	 * Filters story data.
	 *
	 * Provides simple sanity check to ensure story data is valid JSON.
	 *
	 * @since 1.22.0
	 *
	 * @param string $story_data JSON-encoded story data.
	 * @return string Sanitized & slashed story data.
	 */
	private static function filter_story_data(string $story_data, bool $using_unsanitized){
        // if we're using unsanitized data
        if($using_unsanitized){
            // handle the slashes that are added when a post is submitted
            $decoded = json_decode( (string) wp_unslash( $story_data ), true );
            return null === $decoded ? '' : wp_slash( (string) wp_json_encode( $decoded ) );
        }else{
            // if the data isn't unsanitized, it's from the database and doesn't need to be unslashed
            $decoded = json_decode( $story_data, true );
            return null === $decoded ? '' : (string) wp_slash(wp_json_encode( $decoded ));
        }
	}

	/**
	 * Temporarily renames the style attribute to data-temp-style in full story markup.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_content Post content.
	 * @return string Filtered post content.
	 */
	public static function filter_content_save_pre_before_kses($post_content){
		return (string) preg_replace_callback(
			'|(?P<before><\w+(?:-\w+)*\s[^>]*?)style=\\\"(?P<styles>[^"]*)\\\"(?P<after>([^>]+?)*>)|', // Extra slashes appear here because $post_content is pre-slashed..
			static function ( $matches ) {
				return $matches['before'] . sprintf( ' data-temp-style="%s" ', $matches['styles'] ) . $matches['after'];
			},
			$post_content
		);
	}

	/**
	 * Renames data-temp-style back to style in full story markup.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_content Post content.
	 * @return string Filtered post content.
	 */
	public static function filter_content_save_pre_after_kses($post_content){
		return (string) preg_replace_callback(
			'/ data-temp-style=\\\"(?P<styles>[^"]*)\\\"/',
			function($matches){
				$styles = str_replace('&quot;', '\"', $matches['styles']);
				return sprintf(' style="%s"', esc_attr(self::safecss_filter_attr(wp_kses_stripslashes($styles))));
			},
			$post_content
		);
	}

	/**
	 * Filters list of allowed CSS attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param string[]|mixed $attr Array of allowed CSS attributes.
	 * @return array|mixed Filtered list of CSS attributes.
	 */
	public static function filter_safe_style_css( $attr ) {
		if ( ! \is_array( $attr ) ) {
			return $attr;
		}

		$additional = [
			'display',
			'opacity',
			'position',
			'top',
			'left',
			'transform',
			'white-space',
			'clip-path',
			'-webkit-clip-path',
			'pointer-events',
			'will-change',
			'--initial-opacity',
			'--initial-transform',
		];

		array_push( $attr, ...$additional );

		return $attr;
	}

	/**
	 * Filters an inline style attribute and removes disallowed rules.
	 *
	 * This is equivalent to the WordPress core function of the same name,
	 * except that this does not remove CSS with parentheses in it.
	 *
	 * A few more allowed attributes are added via the safe_style_css filter.
	 *
	 * @SuppressWarnings(PHPMD)
	 *
	 * @since 1.0.0
	 *
	 * @see safecss_filter_attr()
	 *
	 * @param string $css A string of CSS rules.
	 * @return string Filtered string of CSS rules.
	 */
	public static function safecss_filter_attr($css){
		$css = wp_kses_no_null( $css );
		$css = str_replace( [ "\n", "\r", "\t" ], '', $css );

		$allowed_protocols = wp_allowed_protocols();

		$css_array = explode( ';', trim( $css ) );

		/** This filter is documented in wp-includes/kses.php */
		$allowed_attr = apply_filters(
			'safe_style_css',
			[
				'background',
				'background-color',
				'background-image',
				'background-position',
				'background-size',
				'background-attachment',
				'background-blend-mode',

				'border',
				'border-radius',
				'border-width',
				'border-color',
				'border-style',
				'border-right',
				'border-right-color',
				'border-right-style',
				'border-right-width',
				'border-bottom',
				'border-bottom-color',
				'border-bottom-style',
				'border-bottom-width',
				'border-left',
				'border-left-color',
				'border-left-style',
				'border-left-width',
				'border-top',
				'border-top-color',
				'border-top-style',
				'border-top-width',

				'border-spacing',
				'border-collapse',
				'caption-side',

				'columns',
				'column-count',
				'column-fill',
				'column-gap',
				'column-rule',
				'column-span',
				'column-width',

				'color',
				'font',
				'font-family',
				'font-size',
				'font-style',
				'font-variant',
				'font-weight',
				'letter-spacing',
				'line-height',
				'text-align',
				'text-decoration',
				'text-indent',
				'text-transform',

				'height',
				'min-height',
				'max-height',

				'width',
				'min-width',
				'max-width',

				'margin',
				'margin-right',
				'margin-bottom',
				'margin-left',
				'margin-top',

				'padding',
				'padding-right',
				'padding-bottom',
				'padding-left',
				'padding-top',

				'flex',
				'flex-basis',
				'flex-direction',
				'flex-flow',
				'flex-grow',
				'flex-shrink',

				'grid-template-columns',
				'grid-auto-columns',
				'grid-column-start',
				'grid-column-end',
				'grid-column-gap',
				'grid-template-rows',
				'grid-auto-rows',
				'grid-row-start',
				'grid-row-end',
				'grid-row-gap',
				'grid-gap',

				'justify-content',
				'justify-items',
				'justify-self',
				'align-content',
				'align-items',
				'align-self',

				'clear',
				'cursor',
				'direction',
				'float',
				'overflow',
				'vertical-align',
				'list-style-type',

				'z-index',
			]
		);

		/*
		 * CSS attributes that accept URL data types.
		 *
		 * This is in accordance to the CSS spec and unrelated to
		 * the sub-set of supported attributes above.
		 *
		 * See: https://developer.mozilla.org/en-US/docs/Web/CSS/url
		 */
		$css_url_data_types = [
			'background',
			'background-image',

			'cursor',

			'list-style',
			'list-style-image',

			'clip-path',
			'-webkit-clip-path',
		];

		/*
		 * CSS attributes that accept gradient data types.
		 *
		 */
		$css_gradient_data_types = [
			'background',
			'background-image',
		];

		/*
		 * CSS attributes that accept color data types.
		 *
		 * This is in accordance to the CSS spec and unrelated to
		 * the sub-set of supported attributes above.
		 *
		 * See: https://developer.mozilla.org/en-US/docs/Web/CSS/color_value
		 */
		$css_color_data_types = [
			'color',
			'background',
			'background-color',
			'border-color',
			'box-shadow',
			'outline',
			'outline-color',
			'text-shadow',
		];

		if ( empty( $allowed_attr ) ) {
			return $css;
		}

		$css = '';
		foreach ( $css_array as $css_item ) {
			if ( '' === $css_item ) {
				continue;
			}

			$css_item        = trim( $css_item );
			$css_test_string = $css_item;
			$found           = false;
			$url_attr        = false;
			$gradient_attr   = false;
			$color_attr      = false;
			$transform_attr  = false;

			$parts = explode( ':', $css_item, 2 );

			if ( false === strpos( $css_item, ':' ) ) {
				$found = true;
			} else {
				$css_selector = trim( $parts[0] );

				if ( \in_array( $css_selector, $allowed_attr, true ) ) {
					$found         = true;
					$url_attr      = \in_array( $css_selector, $css_url_data_types, true );
					$gradient_attr = \in_array( $css_selector, $css_gradient_data_types, true );
					$color_attr    = \in_array( $css_selector, $css_color_data_types, true );

					// --initial-transform is a special custom property used by the story editor.
					$transform_attr = 'transform' === $css_selector || '--initial-transform' === $css_selector;
				}
			}

			if ( $found && $url_attr ) {
				$url_matches = [];

				// Simplified: matches the sequence `url(*)`.
				preg_match_all( '/url\([^)]+\)/', $parts[1], $url_matches );

				foreach ( $url_matches[0] as $url_match ) {
					$url_pieces = [];

					// Clean up the URL from each of the matches above.
					preg_match( '/^url\(\s*([\'\"]?)(.*)(\g1)\s*\)$/', $url_match, $url_pieces );

					if ( empty( $url_pieces[2] ) ) {
						$found = false;
						break;
					}

					$url = trim( $url_pieces[2] );

					if ( empty( $url ) || wp_kses_bad_protocol( $url, $allowed_protocols ) !== $url ) {
						$found = false;
						break;
					}

					// Remove the whole `url(*)` bit that was matched above from the CSS.
					$css_test_string = str_replace( $url_match, '', $css_test_string );
				}
			}

			if ( $found && $gradient_attr ) {
				$css_value = trim( $parts[1] );
				if ( preg_match( '/^(repeating-)?(linear|radial|conic)-gradient\(([^()]|rgb[a]?\([^()]*\))*\)$/', $css_value ) ) {
					// Remove the whole `gradient` bit that was matched above from the CSS.
					$css_test_string = str_replace( $css_value, '', $css_test_string );
				}
			}

			if ( $found && $color_attr ) {
				$color_matches = [];

				// Simplified: matches the sequence `rgb(*)` and `rgba(*)`.
				preg_match_all( '/rgba?\([^)]+\)/', $parts[1], $color_matches );

				foreach ( $color_matches[0] as $color_match ) {
					$color_pieces = [];

					// Clean up the color from each of the matches above.
					preg_match( '/^rgba?\([^)]*\)$/', $color_match, $color_pieces );

					// Remove the whole `rgb(*)` / `rgba(*) bit that was matched above from the CSS.
					$css_test_string = str_replace( $color_match, '', $css_test_string );
				}
			}

			if ( $found && $transform_attr ) {
				$css_value = trim( $parts[1] );
				if ( preg_match( '/^((matrix|matrix3d|perspective|rotate|rotate3d|rotateX|rotateY|rotateZ|translate|translate3d|translateX|translatY|translatZ|scale|scale3d|scalX|scaleY|scaleZ|skew|skewX|skeY)\(([^()])*\) ?)+$/', $css_value ) ) {
					// Remove the whole `gradient` bit that was matched above from the CSS.
					$css_test_string = str_replace( $css_value, '', $css_test_string );
				}
			}

			if ( $found ) {
				// Allow CSS calc().
				$css_test_string = (string) preg_replace( '/calc\(((?:\([^()]*\)?|[^()])*)\)/', '', $css_test_string );
				// Allow CSS var().
				$css_test_string = (string) preg_replace( '/\(?var\(--[a-zA-Z0-9_-]*\)/', '', $css_test_string );

				// Check for any CSS containing \ ( & } = or comments,
				// except for url(), calc(), or var() usage checked above.
				$allow_css = ! preg_match( '%[\\\(&=}]|/\*%', $css_test_string );

				/** This filter is documented in wp-includes/kses.php */
				$allow_css = apply_filters( 'safecss_filter_attr_allow_css', $allow_css, $css_test_string );

				// Only add the CSS part if it passes the regex check.
				if ( $allow_css ) {
					if ( '' !== $css ) {
						$css .= ';';
					}

					$css .= $css_item;
				}
			}
		}

		return $css;
	}


	/**
	 * Filter the allowed tags for KSES to allow for complete amp-story document markup.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
	 *
	 * @since 1.0.0
	 *
	 * @param array|mixed $allowed_tags Allowed tags.
	 * @return array|mixed Allowed tags.
	 */
	public static function filter_kses_allowed_html($allowed_tags){
		if(!\is_array($allowed_tags)){
			return $allowed_tags;
		}

		$story_components = [
			'html'                          => [
				'amp'  => true,
				'lang' => true,
			],
			'head'                          => [],
			'body'                          => [],
			'meta'                          => [
				'name'    => true,
				'content' => true,
				'charset' => true,
			],
			'script'                        => [
				'async'          => true,
				'src'            => true,
				'custom-element' => true,
				'type'           => true,
			],
			'noscript'                      => [],
			'link'                          => [
				'href' => true,
				'rel'  => true,
			],
			'style'                         => [
				'type'            => true,
				'amp-boilerplate' => true,
				'amp-custom'      => true,
			],
			'amp-story'                     => [
				'background-audio'     => true,
				'live-story'           => true,
				'live-story-disabled'  => true,
				'poster-landscape-src' => true,
				'poster-portrait-src'  => true,
				'poster-square-src'    => true,
				'publisher'            => true,
				'publisher-logo-src'   => true,
				'standalone'           => true,
				'supports-landscape'   => true,
				'title'                => true,
			],
			'amp-story-captions'            => [
				'height' => true,
			],
			'amp-story-shopping-attachment' => [
				'cta-text' => true,
				'theme'    => true,
				'src'      => true,
			],
			'amp-story-shopping-config'     => [
				'src' => true,
			],
			'amp-story-shopping-tag'        => [],
			'amp-story-page'                => [
				'auto-advance-after' => true,
				'background-audio'   => true,
				'id'                 => true,
			],
			'amp-story-page-attachment'     => [
				'href'  => true,
				'theme' => true,
			],
			'amp-story-page-outlink'        => [
				'cta-image'          => true,
				'theme'              => true,
				'cta-accent-color'   => true,
				'cta-accent-element' => true,
			],
			'amp-story-grid-layer'          => [
				'aspect-ratio' => true,
				'position'     => true,
				'template'     => true,
			],
			'amp-story-cta-layer'           => [],
			'amp-story-animation'           => [
				'trigger' => true,
			],
			'amp-img'                       => [
				'alt'                       => true,
				'attribution'               => true,
				'data-amp-bind-alt'         => true,
				'data-amp-bind-attribution' => true,
				'data-amp-bind-src'         => true,
				'data-amp-bind-srcset'      => true,
				'disable-inline-width'      => true,
				'lightbox'                  => true,
				'lightbox-thumbnail-id'     => true,
				'media'                     => true,
				'noloading'                 => true,
				'object-fit'                => true,
				'object-position'           => true,
				'placeholder'               => true,
				'sizes'                     => true,
				'src'                       => true,
				'srcset'                    => true,
			],
			'amp-video'                     => [
				'album'                      => true,
				'alt'                        => true,
				'artist'                     => true,
				'artwork'                    => true,
				'attribution'                => true,
				'autoplay'                   => true,
				'captions-id'                => true,
				'controls'                   => true,
				'controlslist'               => true,
				'crossorigin'                => true,
				'data-amp-bind-album'        => true,
				'data-amp-bind-alt'          => true,
				'data-amp-bind-artist'       => true,
				'data-amp-bind-artwork'      => true,
				'data-amp-bind-attribution'  => true,
				'data-amp-bind-controls'     => true,
				'data-amp-bind-controlslist' => true,
				'data-amp-bind-loop'         => true,
				'data-amp-bind-poster'       => true,
				'data-amp-bind-preload'      => true,
				'data-amp-bind-src'          => true,
				'data-amp-bind-title'        => true,
				'disableremoteplayback'      => true,
				'dock'                       => true,
				'lightbox'                   => true,
				'lightbox-thumbnail-id'      => true,
				'loop'                       => true,
				'media'                      => true,
				'muted'                      => true,
				'noaudio'                    => true,
				'noloading'                  => true,
				'object-fit'                 => true,
				'object-position'            => true,
				'placeholder'                => true,
				'poster'                     => true,
				'preload'                    => true,
				'rotate-to-fullscreen'       => true,
				'src'                        => true,
			],
			'source'                        => [
				'type' => true,
				'src'  => true,
			],
			'img'                           => [
				'alt'           => true,
				'attribution'   => true,
				'border'        => true,
				'decoding'      => true,
				'height'        => true,
				'importance'    => true,
				'intrinsicsize' => true,
				'ismap'         => true,
				'loading'       => true,
				'longdesc'      => true,
				'sizes'         => true,
				'src'           => true,
				'srcset'        => true,
				'srcwidth'      => true,
				'width'         => true,
			],
			'svg'                           => [
				'width'   => true,
				'height'  => true,
				'viewbox' => true,
				'fill'    => true,
				'xmlns'   => true,
			],
			'clippath'                      => [
				'transform'     => true,
				'clippathunits' => true,
				'path'          => true,
			],
			'defs'                          => [],
			'feblend'                       => [
				'in'     => true,
				'in2'    => true,
				'result' => true,
			],
			'fecolormatrix'                 => [
				'in'     => true,
				'values' => true,
			],
			'feflood'                       => [
				'flood-opacity' => true,
				'result'        => true,
			],
			'fegaussianblur'                => [
				'stddeviation' => true,
			],
			'feoffset'                      => [],
			'filter'                        => [
				'id'                          => true,
				'x'                           => true,
				'y'                           => true,
				'width'                       => true,
				'height'                      => true,
				'filterunits'                 => true,
				'color-interpolation-filters' => true,
			],
			'g'                             => [
				'filter'  => true,
				'opacity' => true,
			],
			'path'                          => [
				'd'         => true,
				'fill-rule' => true,
				'clip-rule' => true,
				'fill'      => true,
			],
		];

		$allowed_tags = self::array_merge_recursive_distinct($allowed_tags, $story_components);

		$allowed_tags = array_map([__CLASS__, 'add_global_attributes'], $allowed_tags);

		return $allowed_tags;
	}

	/**
	 * Recursively merge multiple arrays and ensure values are distinct.
	 *
	 * Based on information found in http://www.php.net/manual/en/function.array-merge-recursive.php
	 *
	 * @since 1.5.0
	 *
	 * @param array ...$arrays [optional] Variable list of arrays to recursively merge.
	 * @return array An array of values resulted from merging the arguments together.
	 */
	protected static function array_merge_recursive_distinct( array ...$arrays ){
		if ( \count( $arrays ) < 2 ) {
			if ( [] === $arrays ) {
				return $arrays;
			}

			return array_shift( $arrays );
		}

		$merged = array_shift( $arrays );

		foreach ( $arrays as $array ) {
			foreach ( $array as $key => $value ) {
				if ( \is_array( $value ) && ( isset( $merged[ $key ] ) && \is_array( $merged[ $key ] ) ) ) {
					$merged[ $key ] = self::array_merge_recursive_distinct( $merged[ $key ], $value );
				} else {
					$merged[ $key ] = $value;
				}
			}
		}

		return (array) $merged;
	}

	/**
	 * Helper function to add global attributes to a tag in the allowed HTML list.
	 *
	 * @since 1.0.0
	 *
	 * @see _wp_add_global_attributes
	 *
	 * @param array $value An array of attributes.
	 * @return array The array of attributes with global attributes added.
	 */
	protected static function add_global_attributes($value){
		$global_attributes = [
			'aria-describedby'    => true,
			'aria-details'        => true,
			'aria-label'          => true,
			'aria-labelledby'     => true,
			'aria-hidden'         => true,
			'class'               => true,
			'id'                  => true,
			'style'               => true,
			'title'               => true,
			'role'                => true,
			'data-*'              => true,
			'animate-in'          => true,
			'animate-in-duration' => true,
			'animate-in-delay'    => true,
			'animate-in-after'    => true,
			'animate-in-layout'   => true,
			'layout'              => true,
		];

		return (array) array_merge( $value, $global_attributes );
	}

    /**
     * End borrowed Google Web Story code.
     **/

    /**
     * Inserts links into Google Web Story "filtered_post_content".
     * Since the content is JSON encoded, we need a special updater for it.
     **/
    public static function insert_links_in_webstory_json(&$data, $sentence, $changed_sentence, $force_insert){
        if(empty($data)){
            return false;
        }

        // if there's no data or there's no pages
        if(empty($data) || !isset($data['pages']) || empty($data['pages'])){
            // exit
            return false;
        }

        foreach($data['pages'] as &$dat){
            if(!empty($dat) && isset($dat['elements']) && !empty($dat['elements'])){
                foreach($dat['elements'] as &$sub_dat){
                    if( isset($sub_dat['type']) && $sub_dat['type'] === 'text' && 
                        isset($sub_dat['content']) && !empty($sub_dat['content']))
                    {
                        self::insertLink($sub_dat['content'], $sentence, addslashes($changed_sentence), $force_insert);
                    }
                }
            }
        }
    }

    /**
     * Check if it need to force page reload
     */
    function editorReload(){
        if (!empty($_POST['post_id'])) {
            $meta = get_post_meta((int)$_POST['post_id'], 'wpil_gutenberg_restart', true);
            if (!empty($meta)) {
                delete_post_meta((int)$_POST['post_id'], 'wpil_gutenberg_restart');
                echo 'reload';
            }
        }

        wp_die();
    }

    /**
     * Check if outbound links were added to show dialog box
     */
    function isOutboundLinksAdded(){
        if (!empty($_POST['id']) && !empty($_POST['type'])) {
            if ($_POST['type'] == 'term') {
                $meta = get_term_meta((int)$_POST['id'], 'wpil_is_outbound_links_added', true);
            } else {
                $meta = get_post_meta((int)$_POST['id'], 'wpil_is_outbound_links_added', true);
            }
            if (!empty($meta)) {
                if ($_POST['type'] == 'term') {
                    delete_term_meta((int)$_POST['id'], 'wpil_is_outbound_links_added');
                } else {
                    delete_post_meta((int)$_POST['id'], 'wpil_is_outbound_links_added');
                }
                echo 'success';
            }
        }

        wp_die();
    }

    /**
     * Check if inbound links were added to show dialog box
     */
    function isInboundLinksAdded(){
        if (!empty($_POST['id']) && !empty($_POST['type'])) {
            if ($_POST['type'] == 'term') {
                $meta = get_term_meta((int)$_POST['id'], 'wpil_is_inbound_links_added', true);
            } else {
                $meta = get_post_meta((int)$_POST['id'], 'wpil_is_inbound_links_added', true);
            }
            if (!empty($meta)) {
                if ($_POST['type'] == 'term') {
                    delete_term_meta((int)$_POST['id'], 'wpil_is_inbound_links_added');
                } else {
                    delete_post_meta((int)$_POST['id'], 'wpil_is_inbound_links_added');
                }
                echo 'success';
            }
        }

        wp_die();
    }

    /**
     * Ignores the selected orphaned post on the orphaned post view.
     **/
    function ajaxIgnoreOrphanedPost(){
        $post_id = (int)$_POST['post_id'];
        if(empty($post_id)){
            wp_send_json(array('error' => array('title' => __('Post id empty', 'wpil'),'text' => __('The post id was missing from the ignore orphaned post request.', 'wpil'))));
        }

        if(empty(wp_verify_nonce($_POST['nonce'], 'ignore-orphaned-post-' . $post_id))){
            wp_send_json(array('error' => array('title' => __('Expired data', 'wpil'),'text' => __('Some of the data was too old to process, please reload the page and try again.', 'wpil'))));
        }

        // get the post
        $post = new Wpil_Model_Post($post_id, sanitize_text_field($_POST['type']));

        // get the ignored orphaned posts
        $ignored = Wpil_Settings::getIgnoreKeywordsPosts();

        // if the post is ignored, send back that the post is on the list
        if(in_array($post->type . '_' . $post_id, $ignored, true)){
            wp_send_json(array('success' => true));
        }

        $ignored_posts = get_option('wpil_ignore_orphaned_posts', '');

        $ignored_posts .= "\n" . $post->getLinks()->view;

        update_option('wpil_ignore_orphaned_posts', $ignored_posts);

        wp_send_json(array('success' => true));
    }

    /**
     * Filters the post types that the custom link search box will look for so the user is only shown selected post types
     **/
    public static function filter_custom_link_post_types($query_args){
        if(!empty($_POST) && isset($_POST['wpil_custom_link_search'])){
            $selected_post_types = Wpil_Settings::getPostTypes();
            if(!empty($selected_post_types)){
                $query_args['post_type'] = $selected_post_types;
            }
        }
        return $query_args;
    }

    /**
     * Queries for terms when the user does a custom link search for outbound suggestions.
     * The existing search only does posts, so we have to do the terms separately
     **/
    public static function custom_link_category_search($queried_items = array()){
        if(!empty($_POST) && isset($_POST['wpil_custom_link_search'])){

            $selected_terms = get_option('wpil_2_term_types', array());

            if(empty($selected_terms)){
                return $queried_items;
            }

            $args = array('taxonomy' => $selected_terms, 'search' => $_POST['search'], 'number' => 20);

            $term_query = new WP_Term_Query($args);
            $terms = $term_query->get_terms();

            if(empty($terms)){
                return $queried_items;
            }

            foreach($terms as $term){
                $queried_items[] = array(
                    'ID' => $term->term_id,
                    'title' => $term->name,
                    'permalink' => get_term_link($term->term_id),
                    'info' => ucfirst($term->taxonomy),
                );

            }
        }

        return $queried_items;
    }

    /**
     * Insert links into sentence
     *
     * @param $sentence
     * @param $anchor
     * @param $url
     * @param $to_post_id
     * @return string
     */
    public static function getSentenceWithAnchor($link) {
        if (!empty($link['custom_sentence'])) {
            $link['custom_sentence'] = mb_ereg_replace(preg_quote(',</a>'), '</a>,', $link['custom_sentence']);
            return $link['custom_sentence'];
        }

        //get URL
        preg_match('/<a href="([^\"]+)"[^>]*>(.*)<\/a>/i', $link['sentence_with_anchor'], $matches);
        if (empty($matches[1])) {
            return $link['sentence'];
        }

        // update the sentence's tags
        $link['sentence'] = self::update_sentence_tags($link['sentence'], $link['sentence_with_anchor']);

        $url = $matches[1];

        //get anchor from source sentence
        $words = [];
        $word_start = false;
        $word_end = 0;
        preg_match_all('/<span[^>]+>([^<]+)<\/span>/i', $matches[2], $matches);
        if (count($matches[1])) {
            foreach ($matches[1] as $word) {
                if ($word_start === false) {
                    $word_start = stripos($link['sentence'], $word . ' ');
                    if(false === $word_start){
                        $word_start = stripos($link['sentence'], $word);
                    }
                    $word_end = $word_start + strlen($word);
                } else {
                    $word_end = stripos($link['sentence'], $word, $word_end) + strlen($word);
                }

                $words[] = $word;
            }
        }

        //get start position by nearest whitespace
        $start = 0;
        $i = 0;
        while(strpos($link['sentence'], ' ', $start+1) < $word_start && $i < 100) {
            $start = strpos($link['sentence'], ' ', $start+1);
            $next_whitespace = strpos($link['sentence'], ' ', $start+1);
            $tag = strpos($link['sentence'], '>', $start +1);
            if ($tag && $tag < $next_whitespace) {
                $start = $tag;
            }
            $tag = strpos($link['sentence'], '(', $start +1);
            if ($tag && $tag < $next_whitespace) {
                $start = $tag;
            }
            $i++;

            // exit the loop if there's no further whitespace
            if(empty($next_whitespace)){
                break;
            }
        }
        if ($start) {
            $start++;
        }

        $nbsp = urldecode('%C2%A0');

        //get end position by nearest whitespace
        $end = 0;
        $prev_end = 0;
        while($end < $word_end && $end !== false) {
            $prev_end = $end;
            $end = strpos($link['sentence'], ' ', $end + 1);
            $tag = strpos($link['sentence'], ')', $prev_end +1);

            if($end > $word_end){
                $maybe_end = strpos($link['sentence'], $nbsp, $prev_end + 1);
                if(!empty($maybe_end) && $maybe_end < $word_end){
                    $end = $maybe_end;
                }
            }

            if ($tag && $tag < $end) {
                $end = $tag;
            }
        }

        if (substr($link['sentence'], $end-1, 1) == ',') {
            $end -= 1;
        }

        if ($end === false) {
            $end = strlen($link['sentence']);
        }

        $anchor = substr($link['sentence'], $start, $end - $start);

        $external = !Wpil_Link::isInternal($url);
        $open_new_tab = (int)get_option('wpil_2_links_open_new_tab', 0);
        $open_external_new_tab = false;
        if($external){
            $open_external_new_tab = get_option('wpil_external_links_open_new_tab', null);
        }

        //add target blank if needed
        $blank = '';
        $rel = '';
        if (($open_new_tab == 1 && empty($external)) || 
            ($external && $open_external_new_tab) ||
            ($open_new_tab == 1 && $open_external_new_tab === null)
        ) {
            $noreferrer = !empty(get_option('wpil_add_noreferrer', false)) ? ' noreferrer': '';
            $blank = 'target="_blank"';
            $rel = 'rel="noopener' . $noreferrer;
        }

        // if the user has set external links to be nofollow, this is an external link, and this isn't an interlinked site
        if(
            !empty(get_option('wpil_add_nofollow', false)) && 
            $external && 
            !empty(wp_parse_url($url, PHP_URL_HOST)) &&
            !in_array(wp_parse_url($url, PHP_URL_HOST), Wpil_SiteConnector::get_linked_site_domains(), true))
        {
            if(empty($rel)){
                $rel = 'rel="nofollow';
            }else{
                $rel .= ' nofollow';
            }
        }

        // if the user has set some domains to be listed as sponsored
        if(
            $external && 
            !empty(wp_parse_url($url, PHP_URL_HOST)) &&
            Wpil_Link::checkIfSponsoredLink($url))
        {
            if(empty($rel)){
                $rel = 'rel="sponsored';
            }else{
                $rel .= ' sponsored';
            }
        }

        if(!empty($rel)){
            $rel .= '"';
        }

        //add slashes to the anchor if it doesn't found in the sentence
        if (stripos(addslashes($link['sentence']), $anchor) === false) {
//            $anchor = addslashes($anchor);
        }

        $anchor2 = str_replace('$', '\\$', $anchor);

        /**
         * allow the users to add classes to the link
         * @param string The class list
         * @param bool $external Is the link going to an external site?
         * @param string The location of the filter
         **/
        $classes = apply_filters('wpil_link_classes', '', $external, 'suggestions');

        // if the user returned an array, stringify it
        if(is_array($classes)){
            $classes = implode(' ', $classes);
        }

        $classes = (!empty($classes)) ? 'class="' . sanitize_text_field($classes) . '"': '';

        $title = '';
        if(!empty(get_option('wpil_add_destination_title', false))){
            $dest_post = self::getPostByLink($url);

            if(!empty($dest_post)){
                if($dest_post->type === 'post'){
                    $post = get_post($dest_post->id);
                    $title = 'title="'. esc_attr(apply_filters('wpil_link_destination_title', str_replace(array('[', ']'), array('&#91;', '&#93;'), get_the_title($post)), $post)) .'"';
                }else{
                    $term = get_term($dest_post->id);
                    $title = 'title="'. esc_attr(apply_filters('wpil_link_destination_title', str_replace(array('[', ']'), array('&#91;', '&#93;'), $term->name), $term)) .'"';
                }
            }
        }

        // todo build into a separate attr function with the other checks
        $attrs = '';
        if(!empty($title)){
            $attrs .= ' ' . $title;
        }
        if(!empty($blank)){
            $attrs .= ' ' . $blank;
        }
        if(!empty($rel)){
            $attrs .= ' ' . $rel;
        }
        if(!empty($classes)){
            $attrs .= ' ' . $classes;
        }

        // change the staging domain into the live domain if the user has opted to do so
        $url = Wpil_Link::filter_staging_to_live_domain($url);

        //add link to sentence
        $sentence = preg_replace('/'.preg_quote($anchor, '/').'/i', '<a href="'.$url.'"' . $attrs . '>'.$anchor2.'</a>', $link['sentence'], 1);

        $sentence = str_replace('$', '\\$', $sentence);

        // format the tags inside the sentence to make sure there's no half-in half-out tags
        $sentence = self::format_sentence_tags($sentence);

        return $sentence;
    }

    /**
     * Updates the html style tags in the sentence with the results from sentence with anchor.
     **/
    public static function update_sentence_tags($sentence, $sentence_with_anchor){

        // find all the encoded style tags
        preg_match_all('/<span[^><]*?class=["\'][^"\']*?wpil_suggestion_tag[^"\']*?["\'][^>]*?>([^<]*?)<\/span>/', $sentence_with_anchor, $matches);

        if(empty($matches)){
            return $sentence;
        }

        foreach($matches[0] as $key => $match){
            $decoded = base64_decode($matches[1][$key]);
            if(preg_match('/' . preg_quote($match, '/') . '\s*/', $sentence_with_anchor)){
                $sentence_with_anchor = preg_replace('/' . preg_quote($match, '/') . '\s*/', $decoded, $sentence_with_anchor);
            }else{
                $sentence_with_anchor = str_replace($match, $decoded, $sentence_with_anchor);
            }
        }

        // find all the non word tags
        preg_match_all('/<span[^><]*?class=["\'][^"\']*?wpil-non-word[^"\']*?["\'][^>]*?>([^<]*?)<\/span>/', $sentence_with_anchor, $matches);

        // if there are non word tags, remove them so they don't throw off the formatting
        if(!empty($matches)){
            foreach($matches[0] as $key => $match){
                $sentence_with_anchor = str_replace($match, $matches[1][$key], $sentence_with_anchor);
            }
        }

        $new_sentence = strip_tags($sentence_with_anchor, '<b><i><u><strong><em>');

        // remove any tags that are opening and closing without content
        $new_sentence = str_replace(array('<b></b>', '<i></i>', '<u></u>', '<strong></strong>', '<em></em>'), '', $new_sentence);
        $new_sentence = str_replace(array('<b> </b>', '<i> </i>', '<u> </u>', '<strong> </strong>', '<em> </em>'), '', $new_sentence);

        // if the sentences are the same after removing all tags
        if(trim(strip_tags($sentence)) === trim(strip_tags($sentence_with_anchor)) || trim(strip_tags($sentence)) === str_replace('  ', ' ', trim(strip_tags($sentence_with_anchor))) ){
            // update the sentence with the new tagged version
            $sentence = trim($new_sentence);
        }

        return $sentence;
    }

    /**
     * Makes sure there aren't any tags that are half-in/half-out of the anchor tag.
     * Moves any offending tags along the same lines as the JS mover:
     ** If just the closing tag is inside the anchor, move it left until it's outside the anchor.
     ** If just the opening tag is inside the anchor, move it right until it's outside the anchor.
     ** If opening and closing tags are next to each other, remove them.
     **/
    public static function format_sentence_tags($sentence){

        // return the sentence if there's no tags inside the anchor
        if(empty(preg_match('/<a.*?>.*?(<[A-z\/]*?>)<\/a>/', $sentence))){
            return $sentence;
        }

        // get the anchor tag and it's position data
        $link_start = mb_strpos($sentence, '<a href="');
        $link_end = mb_strpos($sentence, '</a>', $link_start);
        $link_length = ($link_end + 4 - $link_start);
        $link = mb_substr($sentence, $link_start, $link_length);
        $link_copy = $link;

        $tags_before_anchor = array();
        $tags_after_anchor = array();

        // check the anchor to see what tags it contains
        $tags_to_check = array('(<b>|<\/b>)', '(<i>|<\/i>)', '(<u>|<\/u>)', '(<strong>|<\/strong>)', '(<em>|<\/em>)');
        foreach($tags_to_check as $tag){
            // if it only contains one tag
            if(preg_match_all('/' . $tag . '/', $link, $matches, PREG_OFFSET_CAPTURE) === 1){
                // extract the tag
                $pulled_tag = $matches[0][0][0];
                // get the tag's position
                $position = $matches[0][0][1];
                // replace the tag in the copied link
                $link_copy = mb_ereg_replace(preg_quote($pulled_tag), '', $link_copy);

                // if the tag is a closing tag
                if(strpos($pulled_tag, '/')){
                    // put it on the list of tags that come before the anchor
                    $tags_before_anchor[$position] = $pulled_tag;
                }else{
                    // if it's an opening tag, put it on the list of tags that come after the anchor
                    $tags_after_anchor[$position] = $pulled_tag;
                }
            }
        }

        // if there are tags that should be moved in front of the anchor
        if(!empty($tags_before_anchor)){
            // sort them to make sure we don't make a mess
            ksort($tags_before_anchor);
            // and insert them before the anchor
            $link_copy = implode('', $tags_before_anchor) . $link_copy;
        }

        // if there are tags that should be moved past the end of the anchor
        if(!empty($tags_after_anchor)){
            // sort them to make sure we don't make a mess
            ksort($tags_after_anchor);
            // and add them after the anchor
            $link_copy = $link_copy . implode('', $tags_after_anchor);
        }

        // replace the old link with the new link
        $sentence = mb_ereg_replace(preg_quote($link), $link_copy, $sentence);

        // remove any double tags // it is possible that a user will have something like <strong><em><u></u></em></strong> that should be removed, but we'll cross that bridge when we get there
        $sentence = str_replace(array('<b></b>', '<i></i>', '<u></u>', '<strong></strong>', '<em></em>'), '', $sentence);
        $sentence = str_replace(array('<b> </b>', '<i> </i>', '<u> </u>', '<strong> </strong>', '<em> </em>'), ' ', $sentence);

        return $sentence;
    }

    /**
     * Get post content
     *
     * @param $post_id integer
     * @return string
     */
    public static function getPostContent($post_id)
    {
        $post = get_post($post_id);

        return !empty($post->post_content) ? $post->post_content : '';
    }

    /**
     * Saves autolinks to posts when they're saved or switched from draft to published.
     * Also fires the URLChanger once the autolinking is complete.
     * In the future, we may want to move it to it's own section to differentiate it from the autolinking.
     * ATM, it's here as a cleanup for "save_post" and to update any old autolinking rules that the user has on his site.
     * @param int $post_id The id of the post that we're saving the links to
     **/
    public static function saveAutolinksToPost($post_id){
        // don't save links for revisions
        if(wp_is_post_revision($post_id)){
            return;
        }

        // make sure the post isn't an auto-draft
        $post = get_post($post_id);
        if(!empty($post) && 'auto-draft' === $post->post_status){
            return;
        }

        // make sure this is for a post type that we track
        if(!in_array($post->post_type, Wpil_Settings::getPostTypes())){
            return;
        }

        // make sure we're in the correct priority
        if(998 !== Wpil_Toolbox::get_current_action_priority()){
            return;
        }

        // if the post processing flag isn't active, or hasn't been activated in the past 5 mins
        if (get_option('wpil_post_procession', 0) < (time() - 300)) {
            // create a new post instance to clear any previously edited data
            $post = new Wpil_Model_Post($post_id);
            Wpil_Keyword::addKeywordsToPost($post);
            Wpil_URLChanger::replacePostURLs($post);
        }
    }

    /**
     * Updates the link stats when a post is saved.
     * Updates both the links report table and the link meta that belongs to the post.
     *
     * @param $post_id
     */
    public static function updateStatMark($post_id, $state = 'updated via hook')
    {
        // don't save links for revisions
        if(wp_is_post_revision($post_id)){
            return;
        }

        // make sure the post isn't an auto-draft
        $post = get_post($post_id);
        if(!empty($post) && 'auto-draft' === $post->post_status){
            return;
        }

        // make sure this is for a post type that we track
        if(!in_array($post->post_type, Wpil_Settings::getPostTypes())){
            return;
        }

        // make sure we're checking the link stats at the end of the processing
        if(99999 !== Wpil_Toolbox::get_current_action_priority()){
            return;
        }

        // clear the meta flag
        update_post_meta($post_id, 'wpil_sync_report3', 0);

        // check if the post is being ignored from Link Whisper
        $completely_ignored = Wpil_Settings::get_completely_ignored_pages();
        if(!empty($completely_ignored) && in_array('post_' . $post_id, $completely_ignored, true)){
            // exit now if it is
            return;
        }

        if (get_option('wpil_option_update_reporting_data_on_save', false)) {
            Wpil_Report::fillMeta();
            if(WPIL_STATUS_LINK_TABLE_EXISTS){
                Wpil_Report::remove_post_from_link_table(new Wpil_Model_Post($post_id));
                Wpil_Report::fillWpilLinkTable();
            }
            Wpil_Report::refreshAllStat();
        }else{
            if(WPIL_STATUS_LINK_TABLE_EXISTS){
                $post = new Wpil_Model_Post($post_id);
                // if the current post has the Thrive builder active, load the Thrive content
                $thrive_active = get_post_meta($post->id, 'tcb_editor_enabled', true);
                if(!empty($thrive_active)){
                    $thrive_content = get_post_meta($post->id, 'tve_updated_post', true);
                    if($thrive_content){
                        $post->setContent($thrive_content);
                    }
                }
                if(Wpil_Report::stored_link_content_changed($post)){
                    // get the fresh post content for the benefit of the descendent methods
                    $post->getFreshContent();
                    // update the links stored in the link table
                    Wpil_Report::update_post_in_link_table($post);
                    // update the meta data for the post
                    Wpil_Report::statUpdate($post, true);
                    // and update the link counts for the posts that this one links to
                    Wpil_Report::updateReportInternallyLinkedPosts($post);
                }

                // if the links haven't changed, reset the processing flag
                update_post_meta($post_id, 'wpil_sync_report3', 1);
            }
        }
    }

    /**
     * Delete all post meta on post delete
     *
     * @param $post_id
     */
    public static function deleteReferences($post_id)
    {
        foreach (array_merge(Wpil_Report::$meta_keys, ['wpil_sync_report3', 'wpil_sync_report2_time']) as $key) {
            delete_post_meta($post_id, $key);
        }
        if(WPIL_STATUS_LINK_TABLE_EXISTS){
            // remove the current post from the links table and the links that point to it
            Wpil_Report::remove_post_from_link_table(new Wpil_Model_Post($post_id), true);
        }
    }

    /**
     * Get linked post Ids for current post
     *
     * @param $post
     * @param bool $return_ids Do we jsut return the linked post ids or the whole link object
     * @return array
     */
    public static function getLinkedPostIDs($post, $return_ids = true)
    {
        $linked_post_ids = array();
        $prevent_twoway_linking = get_option('wpil_prevent_two_way_linking', false);

        // get the inbound post links
        if(WPIL_STATUS_LINK_TABLE_EXISTS){
            $links = Wpil_Report::getCachedReportInternalInboundLinks($post);
        }else{
            $links = Wpil_Report::getInternalInboundLinks($post);
        }

        // if we're to prevent twoway linking
        if($prevent_twoway_linking){
            // get the outbound links
            $outbound_links = Wpil_Report::getOutboundLinks($post);
            if(!empty($outbound_links['internal'])){
                $links = array_merge($links, $outbound_links['internal']);
            }
        }

        // if we're supposed to return just the ids
        if($return_ids){
            // process out the ids
            $linked_post_ids[] = $post->id;

            foreach ($links as $link) {
                if (!empty($link->post->id)) {
                    $linked_post_ids[] = $link->post->id;
                }
            }
        }else{
            $url = $post->getLinks()->view;
            $host = parse_url($url, PHP_URL_HOST);


            $linked_post_ids[] = new Wpil_Model_Link([
                'url' => $url,
                'host' => str_replace('www.', '', $host),
                'internal' => Wpil_Link::isInternal($url),
                'post' => $post,
                'anchor' => '',
            ]);

            $linked_post_ids = array_merge($linked_post_ids, $links);
        }

        return $linked_post_ids;
    }

    /**
     * Get all Advanced Custom Fields names
     *
     * @return array
     */
    public static function getAdvancedCustomFieldsList($post_id)
    {
        global $wpdb;

        $fields = [];

        if(!class_exists('ACF') || get_option('wpil_disable_acf', false)){
            return $fields;
        }

        // get any ACF fields the user has ignored
        $ignored_fields = Wpil_Settings::getIgnoredACFFields();

        $fields_query = $wpdb->get_results("SELECT SUBSTR(meta_key, 2) as `name` FROM {$wpdb->postmeta} WHERE post_id = $post_id AND meta_value IN (SELECT DISTINCT post_name FROM {$wpdb->posts} WHERE post_name LIKE 'field_%') AND SUBSTR(meta_key, 2) != ''");
        foreach ($fields_query as $field) {
            $name = trim($field->name);
            if(in_array($name, $ignored_fields, true)){
                continue;
            }

            if ($name) {
                $fields[] = $field->name;
            }
        }

        // if there are any fields created with PHP/JSON
        $local_field_groups = (function_exists('acf_get_local_store')) ? acf_get_local_store('groups') : false;
        if(!empty($local_field_groups) && isset($local_field_groups->data)){
            $search_fields = array();
            foreach($local_field_groups->data as $group){
                // go to some pains to ignore options pages
                if( isset($group['location']) &&
                    isset($group['location'][0]) &&
                    isset($group['location'][0][0]) &&
                    isset($group['location'][0][0]['param']) &&
                    $group['location'][0][0]['param'] == 'options_page' &&
                    $group['location'][0][0]['operator'] == '==')
                {
                    continue;
                }

                if(isset($group['name'])){
                    $search_fields[] = $group['name'];
                }
            }

            if(!empty($search_fields)){
                $search_fields = '`meta_key` LIKE \'' . implode('_%\' OR `meta_key` LIKE \'', $search_fields) . '_%\'';

                $fields_query = $wpdb->get_results("SELECT meta_key as 'name' FROM {$wpdb->postmeta} WHERE `post_id` = $post_id AND ({$search_fields})  AND `meta_value` != ''");

                if(!empty($fields_query)){
                    foreach ($fields_query as $field) {
                        $name = trim($field->name);
                        if(in_array($name, $ignored_fields, true)){
                            continue;
                        }
            
                        if ($name) {
                            $fields[] = $field->name;
                        }
                    }
                }
            }

            // remove any duplicate fields
            $fields = array_flip(array_flip($fields));
        }

        return $fields;
    }

    /**
     * Gets an array of all custom fields on the site.
     * @return array
     **/
    public static function getAllCustomFields()
    {
        global $wpdb;

        if(!class_exists('ACF') || get_option('wpil_disable_acf', false)){
            return array();
        }

        if (self::$advanced_custom_fields_list === null) {
            $fields = [];
            $ignored_fields = Wpil_Settings::getIgnoredACFFields();
            $result = $wpdb->get_results("SELECT DISTINCT post_name FROM {$wpdb->posts} WHERE post_name LIKE 'field_%'");
            $post_names = [];
            foreach ($result as $r) {
                $post_names[] = $r->post_name;
            }

            if (!empty($post_names)) {
                $fields_query = $wpdb->get_results("SELECT DISTINCT meta_key as `name` FROM {$wpdb->postmeta} WHERE meta_value IN ('" . implode("', '", $post_names) . "')");
                foreach ($fields_query as $field) {
                    $name = substr($field->name, 1);
                    if (trim($name) && !in_array($name, $ignored_fields, true)) {
                        $fields[] = $name;
                    }
                }
            }

            // if there are any fields created with PHP/JSON
            $local_field_groups = (function_exists('acf_get_local_store')) ? acf_get_local_store('groups') : false;
            if(!empty($local_field_groups) && isset($local_field_groups->data)){
                $search_fields = array();
                foreach($local_field_groups->data as $group){
                    // go to some pains to ignore options pages
                    if( isset($group['location']) &&
                        isset($group['location'][0]) &&
                        isset($group['location'][0][0]) &&
                        isset($group['location'][0][0]['param']) &&
                        $group['location'][0][0]['param'] == 'options_page' &&
                        $group['location'][0][0]['operator'] == '==')
                    {
                        continue;
                    }

                    if(isset($group['name'])){
                        $search_fields[] = $group['name'];
                    }
                }

                if(!empty($search_fields)){
                    $search_fields = '`meta_key` LIKE \'' . implode('_%\' OR `meta_key` LIKE \'', $search_fields) . '_%\'';

                    $fields_query = $wpdb->get_results("SELECT DISTINCT meta_key as `name` FROM {$wpdb->postmeta} WHERE ({$search_fields})");

                    if(!empty($fields_query)){
                        foreach ($fields_query as $field) {
                            $name = trim($field->name);
                            if(in_array($name, $ignored_fields, true)){
                                continue;
                            }
                
                            if ($name) {
                                $fields[] = $field->name;
                            }
                        }
                    }
                }

                // remove any duplicate fields
                $fields = array_flip(array_flip($fields));
            }

            self::$advanced_custom_fields_list = $fields;
        }

        return self::$advanced_custom_fields_list;
    }

    /**
     * Adds link to a specific metafield, updates the supplied content with the link
     **/
    public static function addLinkToField(&$content, $link = array()){
        $force_insert = (isset($link['keyword_data']) && !empty($link['keyword_data']->force_insert)) ? true: false;
        $changed_sentence = self::getSentenceWithAnchor($link);
        return self::insertLink($content, $link['sentence'], $changed_sentence, $force_insert);
    }

    /**
     * Add link to the content in advanced custom fields
     *
     * @param $link
     * @param $post
     */
    public static function addLinkToAdvancedCustomFields($post_id)
    {
        // don't save the data if this is the result of using wp_update_post // there's no form submission, so $_POST will be empty
        if(empty($_POST)){
            return;
        }

        $meta = get_post_meta($post_id, 'wpil_links', true);

        // if there are links to insert
        if (!empty($meta)) {
            // see if the post has ACF fields
            $fields = self::getAdvancedCustomFieldsList($post_id);
            // if it does
            if (!empty($fields)) {
                // go over each field
                foreach ($fields as $field) {
                    // see if the field has string content
                    $content = get_post_meta($post_id, $field, true);
                    if(!empty($content) && is_string($content)){
                        // and see if we can put a link in it
                        foreach($meta as $key => $link){
                            if (strpos($content, $link['sentence']) !== false) {
                                // if the link was inserted
                                if(self::addLinkToField($content, $link)){
                                    // remove the link from the metadata
                                    unset($meta[$key]);
                                    // and update the field data
                                    update_post_meta($post_id, $field, $content);
                                }
                            }
                        }
                    }
                }

                // update the link meta with the remaining links
                update_post_meta($post_id, 'wpil_links', array_values($meta));
            }
        }
    }

    /**
     * Add link to the content in metafields
     *
     * @param $link
     * @param $post
     */
    public static function addLinkToMetaContent($post_id)
    {
        // don't save the data if this is the result of using wp_update_post // there's no form submission, so $_POST will be empty
        if(empty($_POST)){
            return;
        }

        // make sure this is for a post type that we track
        $post_type = get_post_type($post_id);
        if(empty($post_type) || !in_array($post_type, Wpil_Settings::getPostTypes())){
            return;
        }

        // make sure we're checking the link stats at the right stage of processing
        if(9999 !== Wpil_Toolbox::get_current_action_priority()){
            return;
        }

        $meta = get_post_meta($post_id, 'wpil_links', true);

        if (!empty($meta)) {
            $fields = self::getMetaContentFieldList('post');
            if (!empty($fields)) {
                foreach ($fields as $field) {
                    if ($content = get_post_meta($post_id, $field, true)) {
                        foreach ($meta as $key => $link) {
                            if (strpos($content, $link['sentence']) !== false) {
                                $force_insert = (isset($link['keyword_data']) && !empty($link['keyword_data']->force_insert)) ? true: false;
                                $changed_sentence = self::getSentenceWithAnchor($link);
                                $inserted = self::insertLink($content, $link['sentence'], $changed_sentence, $force_insert);

                                // if the link has been inserted
                                if($inserted){
                                    // remove it from the link meta
                                    unset($meta[$key]);
                                    // and update the field
                                    update_post_meta($post_id, $field, $content);
                                }
                            }
                        }
                    }
                }

                // update the link meta with the remaining links
                update_post_meta($post_id, 'wpil_links', array_values($meta));
            }

            // add links to any ACF fields
            self::addLinkToAdvancedCustomFields($post_id);
            // add links to Oxygen content
            $fake_content = false;
            Wpil_Editor_Oxygen::addLinks($meta, $post_id, $fake_content);
            // add links to Goodlayers content
            Wpil_Editor_Goodlayers::addLinks($meta, $post_id, $fake_content);

            /**
             * Add the links to any custom data fields the customer may have
             * @param int $post_id
             * @param string $post_type (post|term)
             * @param array $meta
             **/
            do_action('wpil_meta_content_data_add_link', $post_id, 'post', $meta);

            //remove DB record with links
            delete_post_meta($post_id, 'wpil_links');
        }
    }

    /**
     * Gets a list of the possible meta content fields to add links to
     * @param string $type Is the content for a post or a term?
     * @return array $fields An array of the possible fields for the item
     **/
    public static function getMetaContentFieldList($type = 'post'){
        $fields = Wpil_Settings::getCustomFieldsToProcess();

        if(defined('RH_MAIN_THEME_VERSION') && $type === 'term'){
            $fields[] = 'brand_second_description';
        }

        return $fields;
    }

    /**
     * Get all posts with the same language
     *
     * @param $post_id
     * @return array
     */
    public static function getSameLanguagePosts($post_id)
    {
        global $wpdb;
        $ids = [];
        $posts = [];

        // if WPML is active and there's languages saved
        if(defined('WPML_PLUGIN_BASENAME')) {
            $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}icl_languages'");
            if($table == $wpdb->prefix . 'icl_languages'){
                $post_types = self::getSelectedLanguagePostTypes();
                $language = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $post_id AND `element_type` IN ({$post_types}) ");
                if (!empty($language)) {
                    $posts = $wpdb->get_results("SELECT element_id as id FROM {$wpdb->prefix}icl_translations WHERE element_id != $post_id AND language_code = '$language' AND `element_type` IN ({$post_types}) ");
                }
            }
        }

        // if Polylang is active
        if(defined('POLYLANG_VERSION')){
            $taxonomy_id = $wpdb->get_var("SELECT t.term_taxonomy_id FROM {$wpdb->term_taxonomy} t INNER JOIN {$wpdb->term_relationships} r ON t.term_taxonomy_id = r.term_taxonomy_id WHERE t.taxonomy = 'language' AND r.object_id = " . $post_id);
            if (!empty($taxonomy_id)) {
                $posts = $wpdb->get_results("SELECT object_id as id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = $taxonomy_id AND object_id != $post_id");
            }
        }

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $ids[] = $post->id;
            }
        }

        return $ids;
    }

    /**
     * Gets the selected post types formatted for WPML
     **/
    public static function getSelectedLanguagePostTypes(){
        $post_types = implode("', 'post_", Wpil_Suggestion::getSuggestionPostTypes());

        if(!empty($post_types)){
            $post_types = "'post_" . $post_types . "'";
        }

        return $post_types;
    }

    public static function getAnchors($post)
    {
        preg_match_all('|<a [^>]+>([^<]+)</a>|i', $post->getContent(), $matches);

        if (!empty($matches[1])) {
            return $matches[1];
        }

        return [];
    }

    /**
     * Get URLs from post content
     *
     * @param $post
     * @return array|mixed
     */
    public static function getUrls($post)
    {
        preg_match_all('#<a\s.*?(?:href=[\'"](.*?)[\'"]).*?>#is', $post->getContent(), $matches);

        if (!empty($matches[1])) {
            return $matches[1];
        }

        return [];
    }

    public static function getSentencesWithUrls($post)
    {
        $data = [];
        $content = $post->getContent();

        // replace any base64ed image urls
        $content = preg_replace('`src="(data:image\/(png|jpeg);base64,[a-zA-Z0-9\/+=]+)"`', '', $content);
        $content = preg_replace('`alt="(Source: data:image\/(png|jpeg);base64,[a-zA-Z0-9\/+=]+)"`', '', $content);

        preg_match_all('`(\!|\?|\.|^|)[^.!?\n]*<a\s[^>]*?(?:href=([\'"]|\\\")(.*?)([\'"]|\\\"))[^>]*?>.*?<\/a>((?!<a)[^.!?\n])*`is', $content, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            if (!empty($matches[0][$i]) && !empty($matches[3][$i])) {
                $sentence = $matches[0][$i];
                if (in_array(substr($sentence, 0, 1), ['.', '!', '?'])) {
                    $sentence = substr($sentence, 1);
                }

                $url = $matches[3][$i];

                // if the url is inside slashed quotes
                if( !empty($matches[2][$i]) && $matches[2][$i] === '\"' &&
                    !empty($matches[4][$i]) && $matches[4][$i] === '\"')
                {
                    // add the quotes to the url
                    $url = ($matches[2][$i] . $url . $matches[4][$i]);
                }

                $data[] = [
                    'sentence' => trim(strip_tags($sentence)),
                    'url' => $url
                ];
            }
        }

        // get the image tags too
        preg_match_all('#<img\s[^>]*?(?:(?:href|src)=([\'"]|\\\")(.*?)([\'"]|\\\"))[^>]*?>#is', $content, $matches);
        if(!empty($matches)){
            for ($i = 0; $i < count($matches[0]); $i++) {
                if (!empty($matches[0][$i]) && !empty($matches[1][$i])) {
                    $text = $matches[0][$i];

                    if(false !== strpos($text, 'title="') && false === strpos($text, 'title=""')){
                        $offset = (mb_strpos($text, 'title="') + 7);
                        $sentence = __('Broken Image. The title is: ', 'wpil') . '"' . mb_substr($text, $offset, (mb_strpos($text, '"', $offset) - $offset) ) . '"';
                    }elseif(false !== strpos($text, 'alt="') && false === strpos($text, 'alt=""')){
                        $offset = (mb_strpos($text, 'alt="') + 5);
                        $sentence = __('Broken Image. The alt text is: ', 'wpil') . '"' . mb_substr($text, $offset, (mb_strpos($text, '"', $offset) - $offset) ) . '"';
                    }else{
                        $sentence = __('Broken Image. The image doesn\'t have a title or alt text.', 'wpil');
                    }

                    $url = $matches[2][$i];

                    // if the url is inside slashed quotes
                    if( !empty($matches[1][$i]) && $matches[1][$i] === '\"' &&
                        !empty($matches[3][$i]) && $matches[3][$i] === '\"')
                    {
                        // add the quotes to the url
                        $url = ($matches[1][$i] . $url . $matches[3][$i]);
                    }

                    $data[] = [
                        'sentence' => trim(strip_tags($sentence)),
                        'url' => $url
                    ];
                }
            }
        }

        // check to make sure that there aren't any empty anchors present
        if(strpos($content, '<a>') !== false){
            // if there are, pull those links too
            preg_match_all('`(\!|\?|\.|^|)[^.!?\n]*<a>(.*?)<\/a>((?!<a)[^.!?\n])*`is', $content, $matches);
            for ($i = 0; $i < count($matches[0]); $i++) {
                if (!empty($matches[0][$i])) {
                    $sentence = $matches[0][$i];
                    if (in_array(substr($sentence, 0, 1), ['.', '!', '?'])) {
                        $sentence = substr($sentence, 1);
                    }
    
                    $anchor = !empty($matches[2][$i]) ? $matches[2][$i]: '';

                    $data[] = [
                        'sentence' => trim(strip_tags($sentence)),
                        'url' => '{{wpil-empty-url}}',
                        'anchor' => $anchor
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Change sentence if it located inside embedded ACF blocks.
     * Changes the double qoutes in the link to insert's attributes into single quotes so we don't break the ACF blocks
     *
     * @param $content
     * @param $sentence
     * @param $changed_sentence
     * @return string
     */
    public static function changeByACF($content, $sentence, $changed_sentence){
        //find all blocks
        $blocks = [];
        $end = 0;
        while(strpos($content, '<!-- wp:acf', $end) !== false) {
            $begin = strpos($content, '<!-- wp:acf', $end);
            $end = strpos($content, '-->', $begin);
            $blocks[] = [$begin, $end];
        }

        //change sentence
        if (!empty($blocks)) {
            $pos = strpos($content, $sentence);
            foreach ($blocks as $block) {
                if ($block[0] < $pos && $block[1] > $pos) {
                    $changed_sentence = str_replace('"', "'", $changed_sentence);
                }
            }
        }

        return $changed_sentence;
    }

    /**
     * Get post model by view link
     *
     * @param $link
     * @return Wpil_Model_Post|null
     */
    public static function getPostByLink($link)
    {
        global $wpdb;
        $post = null;

        // check to see if the link isn't a pretty link
        if(preg_match('#[?&](p|page_id|attachment_id)=(\d+)#', $link, $values)){
            // if it's not, get the id
            $id = absint($values[2]);
            // if there is an id
            if($id){
                // get the post so we can make sure it exists
                $wp_post = get_post($id);
                // if it does exist, set the id. Else, set it to null
                $post_id = (!empty($wp_post)) ? $wp_post->ID: null;
            }
        }else{
            // change the live domain into the staging domain if the user has opted to do so
            $link = Wpil_Link::filter_live_to_staging_domain($link);
            $post_id = url_to_postid($link);
        }

        if (!empty($post_id)) {
            $post = new Wpil_Model_Post($post_id);
        } else {
            $slug = array_filter(explode('/', $link));
            $term = Wpil_Term::getTermBySlug(end($slug), $link);
            if(!empty($term)){
                $post = new Wpil_Model_Post($term->term_id, 'term');
            }
        }

        // if all that didn't work, the post might be draft
        // so we'll try pulling the post name from the URL and seeing if that will get us an id
        if(empty($post) && is_string($link) && !empty($link) && Wpil_Link::isInternal($link)){
            // get the permalink structure
            $link_structure = get_option('permalink_structure', '');
            if(!empty($link_structure)){
                // see if the post name is in it
                if(false !== strpos($link_structure, '%postname%')){
                    // if it is, blow up the link structure
                    $exploded_structure = explode('/', '/' . trim($link_structure, '/') . '/'); // frame the permalink with "/" so that we're consistently comparing it to the link
                    // make the supplied link relative, and blow it up too
                    if(!Wpil_URLChanger::isRelativeLink($link)){
                        // get the home url and clean it up
                        $site_url = get_home_url();
                        $site_url = preg_replace('/http:\/\/|https:\/\/|www\./', '', $site_url);
                        // make sure the supplied link is similarly clean
                        $link = preg_replace('/http:\/\/|https:\/\/|www\./', '', $link);

                        // and replace the home portion of the link to make it relative
                        $link = '/'. trim(str_replace($site_url, '', $link), '/') . '/'; // we're going to assume that the user isn't using a draft post as the home url... That would give us just "/" at this point, and "///" isn't a valid url
                    }

                    // now blow up the link
                    $exploded_link = explode('/', $link);

                    // and see if the link has a postname in the same position as the permalink structure
                    $name = '';
                    foreach($exploded_structure as $key => $piece){
                        if( $piece === '%postname%' &&          // if we're focussed on the postname
                            isset($exploded_link[$key]) &&      // and there's a corresponding piece in the link
                            !empty($exploded_link[$key]) &&     // and there's something in the corresponding piece
                            is_string($exploded_link[$key]) &&  // and the corresponding is a string
                            strlen($exploded_link[$key]) > 0)   // and it's at least 1 char long
                        {
                            // extract the piece as the post name and exit the loop
                            $name = $exploded_link[$key];
                            break;
                        }
                    }

                    // if we've found something
                    if(!empty($name)){
                        // see if there's a post in the database with the same name
                        $dat = $wpdb->get_col($wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE `post_name` = %s AND `post_status` = 'draft' LIMIT 1", $name));

                        // if that didn't work, try looking for the title
                        if(empty($dat)){
                            // replace any hyphens with spaces
                            $name = str_replace('-', ' ', $name);
                            $dat = $wpdb->get_col($wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE `post_status` = 'draft' AND `post_title` = %s LIMIT 1", $name)); // for exceedingly long titles, I might consider re-adding the LIKE check. But we'll cross that bridge when we get there
                        }

                        // if we've found a post id
                        if(!empty($dat) && isset($dat[0]) && !empty($dat[0])){
                            // create the post object we've been striving for
                            $post = new Wpil_Model_Post($dat[0]);
                        }
                    }
                }
            }
        }

        return $post;
    }

    /**
     * Insert link into content
     *
     * @param $content The post content or content segment to update
     * @param $sentence The sentence in the content that will have a link inserted
     * @param $changed_sentence The sentence with a link inserted
     * @param $ignore_links Should we overlook links that are inside the phrase? Currently applies to autolinks that are set to "force_insert" links.
     * @return bool Returns true if the link was inserted, and false if it could not be inserted or data was missing
     */
    public static function insertLink(&$content, $sentence, $changed_sentence, $ignore_links = false)
    {
        if(empty($sentence)){
            return false;
        }

        // if the content already has the link, exit
        if( false !== mb_strpos($content, $changed_sentence) ||
            false !== mb_strpos(self::normalize_slashes($content), self::normalize_slashes($changed_sentence))){ // do a double check to make sure the quotes are the same for content and sentence
            return false;
        }

        $position_start = mb_strpos($content, $sentence, 0);
        if(false === $position_start){
            $position_start = mb_strpos(self::normalize_slashes($content), self::normalize_slashes($sentence), 0);

            // if we have a start point now that the slashes have been normalized
            if(false !== $position_start){
                // find out if normalizing the slashes has changed the start position
                $letter1 = mb_substr(self::normalize_slashes($content), $position_start, 1);
                $letter2 = mb_substr($content, $position_start, 1);
                // if the letters don't match
                if($letter1 !== $letter2){
                    // figure out how far the string has changed to calculate the correct start point
                    $search = mb_substr(self::normalize_slashes($content), $position_start, mb_strlen($sentence));

                    // clean up the string to hopefully get a good search term
                    $search = explode('{wpil-explode-token}', str_replace(array('\'', '"', '\\'), '{wpil-explode-token}', $search));

                    $term = '';
                    foreach($search as $part){
                        if(strlen($part) > strlen($term)){
                            $term = $part;
                        }
                    }

                    // if we've found a term
                    if(!empty($term)){
                        $start1 = mb_strpos($content, $term);
                        $start2 = mb_strpos(self::normalize_slashes($content), $term);

                        if(false !== $start1 && false !== $start2){
                            if($start1 < $start2){ // 29 < 30; 30 - 29 = 1; 
                                $position_start = $position_start - ($start2 - $start1);
                            }else{ // 30 > 29; 30 - 29 = 1;
                                $position_start = $position_start + ($start1 - $start2);
                            }
                        }else{
                            $position_start = abs(intval($start1) - intval($start2));
                        }

                    }else{
                        $position_start = 0;
                    }
                }
            }
        }

        $position_end = 0;
        $old_end = 0;
        $endings = array_diff(Wpil_Word::$endings, array('\'', '"', ','));
        $sent_len = mb_strlen($sentence);

        // while we have words
        while($position_start !== false){

            // go over all the endings and find out which one is the actual end to the current string
            $shortest = false;
            foreach($endings as $ending){
                // the shortest string will have the ending punctuation
                $current_end = mb_strpos($content, $ending, ($position_start + $sent_len));
                if(false === $shortest){
                    $shortest = $current_end;
                }elseif($current_end < $shortest && $current_end !== false){
                    $shortest = $current_end;
                }
            }

            $position_end = (false !== $shortest) ? $shortest: mb_strlen($content); // if no ending was found, give the end of the content

            // now find the ending of the string that comes before the current one.
            $old_shortest = false;
            foreach($endings as $ending){
                // the longest string will have the ending punctuation since it's closest to the end of the current string.
                $current_end = mb_strrpos($content, $ending, (1 + $position_start - mb_strlen($content)));

                if(false === $current_end){
                    continue;
                }

                // if there's a closing html tag that comes after the current old ending
                $closing = mb_strpos($content, '>', $current_end);
                if(false !== $closing && $closing < $position_end){
                    // find the opening tag so we can tell what kind of tag this is
                    $current_end = mb_strrpos($content, '<', (1 + $current_end - mb_strlen($content)));
                }

                if(false === $old_shortest){
                    $old_shortest = $current_end;
                }elseif($current_end > $old_shortest && $current_end !== false){
                    $old_shortest = $current_end;
                }
            }

            $old_end = (false !== $old_shortest) ? $old_shortest: 0;
            $length = ($position_end - $position_start);
            $replace = mb_substr($content, $position_start, $length);

            // get the slice of text that we'll be checking for links
            $examine_text = mb_substr($content, $old_end, ($position_end - $old_end));

            // if there isn't a link in the text AND the text isn't inside of a tag
            if( !Wpil_Link::checkForForbiddenTags($examine_text, $replace, $sentence, $ignore_links) && 
                !self::check_if_inside_tag($content, $position_start, $position_end))
            {
                // get the text that comes before and after the sentence
                $front = mb_substr($content, 0, $position_start);
                $back = mb_substr($content, ($position_start + $length));

                // remove any quotes from the sentence to change
                $changed_sentence = Wpil_Word::removeQuotes($changed_sentence);

                // check if the user only wants to insert relative links
                if(!empty(get_option('wpil_insert_links_as_relative', false))){
                    // if he does, extract the url
                    preg_match('/<a href="([^\"]+)"[^>]*?>(.*)<\/a>/i', $changed_sentence, $matches);

                    // if we've got the url
                    if(!empty($matches) && isset($matches[1])){
                        // check the url to make sure it's internal
                        if(Wpil_Link::isInternal($matches[1])){
                            // if it is, make it relative
                            $url = wp_make_link_relative($matches[1]);
                            // and replace the existing url with the new one
                            $changed_sentence = mb_ereg_replace(preg_quote($matches[1]), $url, $changed_sentence);
                        }
                    }
                }

                // escape the link if it's in json content
                $changed_sentence = self::processLinkForJsonContent($content, $changed_sentence, $position_start, $position_end);

                $changed_text = mb_ereg_replace('(?<!=[\"\'\\\"\\\'])(' . preg_quote($sentence) . ')(?![\"\'\\\"\\\'].*?>)', $changed_sentence, $replace);

                // if the link has been inserted multiple times
                if(substr_count($changed_text, '</a>') > 1){
                    // remove all but the first version of the link
                    global $wpil_link_insert_count, $wpil_link_insert_sentence;
                    $wpil_link_insert_count = 0;
                    $wpil_link_insert_sentence = $sentence;

                    $changed_text = mb_ereg_replace_callback(preg_quote($changed_sentence), function($matches){global $wpil_link_insert_count, $wpil_link_insert_sentence; $wpil_link_insert_count++; return ($wpil_link_insert_count === 1) ? $matches[0] : $wpil_link_insert_sentence; }, $changed_text);
                }

                // if the link has been inserted
                if($changed_text !== $replace){
                    // add the link to the text
                    $content = ($front . $changed_text . $back);
                    // and exit the loop since we only add one link at a time.
                    return true;
                }else{
                    // if the link couldn't be inserted, continue the loop so hopefully we find the place to insert the link
                    $position_start = mb_strpos($content, $sentence, $position_end + 1);
                }
            }else{
                // if the keyword text is in a link, move to the next instance of the keyword
                try {
                    $position_start = mb_strpos($content, $sentence, $position_end + 1);
                } catch (Throwable $t) {
                    $position_start = false;
                } catch (Exception $e) {
                    $position_start = false;
                }
            }
        }

        return false;
    }

    /**
     * Checks to see if the current slice of text is in the middle of a tag.
     * Currently, scans the text backwards from the start position to see if it can find an opening HTML tag before coming across a closer.
     * If it can't find the tag, it assumes that the link isn't inside of a tag
     * @param string $content The body content that the link will be inserted in.
     * @param int $position_start The start of the "replace" text
     * @param int $position_end The ending of the "replace" text
     * @return bool Returns true if the text is inside of a tag, and false if it's not || there are no tags || it can't tell
     **/
    public static function check_if_inside_tag($content, $position_start, $position_end){
        // if we're missing data, we can't be inside a tag... Or at least we can't know it
        if(empty($content) || false === $position_start || false === $position_end){
            return false;
        }

        // check to make sure the content has HTML tags
        $has_normal = (false !== strpos($content, '<') && false !== strpos($content, '>'));
        $has_encoded = (false !== strpos($content, '&gt;') && false !== strpos($content, '&lt;'));

        // if we don't appear to have tags, say that we're not in a tag
        if(!$has_normal && !$has_encoded){
            return false;
        }

        $inside_tag = null;
        $tag_name_chars = '[a-zA-Z-]+'; // the list of chars that can be in a tag name

        // if the start position is past the beginning of the content
        if($position_start > 0){
            // start counting letters backwards to see if we can find an opening HTML tag
            for($i = ($position_start - 1); $i >= 0; $i--){
                $char = mb_substr($content, $i, 1);

                // if we find what looks like a closing tag (before finding what might be an opening tag)
                if($char === '>' || $char === '&gt;'){
                    // check to make sure it's a closing tag
                    for($j = ($i - 1); $j >= 0; $j--){
                        // find the opening char "<" for the possible closing tag
                        $tag_char = mb_substr($content, $j, 1);
                        if($tag_char === '<' || $tag_char === '&lt;'){
                            // extract the tag
                            $tag = mb_substr($content, $j, ($i - $j) + 1);
                            // check to see if it's a closing tag
                            $closing = preg_match('/'.$tag_char . '\/' . $tag_name_chars . $char.'/', $tag);
                            // if it is
                            if(!empty($closing)){
                                // we _shouldn't_ be inside of an HTML tag, so set the flag for it
                                $inside_tag = false;
                                // and exit the loops
                                break 2;
                            }else{
                                // if it's not a closing tag, see if it's an opening one
                                /*$opening = preg_match('/'.$tag_char . $tag_name_chars . $char.'/', $tag);
                                // if it's an opening tag
                                if(!empty($opening)){
                                    // see if there's any signs of opening HTML tags between here and where we started

                                }*/

                                // decide that we've done enough looking and say that we're not in a tag
                                $inside_tag = false;
                                // and exit both loops
                                break 2;
                                // exit the tag loop
                                //break;
                            }
                        }
                    }

                }elseif($char === '<' || $char === '&lt;'){
                    // if the current char is the opening sign for a tag

                    // track back down the letters to put together a potential tag
                    $tag_name = '';
                    for($k = ($i + 1); $k <= $position_end; $k++){
                        $tag_char = mb_substr($content, $k, 1);
                        $valid_char = preg_match('/'.$tag_name_chars.'/', $tag_char);

                        // if the char can be in a tag name
                        if($valid_char){
                            // add it to our provisional tag name
                            $tag_name .= $tag_char;
                        }elseif(!empty($tag_name) && empty($valid_char)){
                            // if we have a tag name, and the current char isn't recognized as an html tag
                            // check the content for a closing version of it
                            $closing_tag = preg_match('/'. $char . '\/' .$tag_name.'(?:>|&gt;)/', $content);
                        
                            // if there is a closing version of it
                            if(!empty($closing_tag)){
                                // we're inside a tag!
                                $inside_tag = true;
                                // exit the loops
                                break 2;
                            }
                        }else{
                            // if the first letter isn't part of a tag, break out of this loop
                            break;
                        }
                    }
                }
            }
        }

        // TODO: Check from the ending part of the string if the need ever arises...
        // Since it takes 2 ends to make a tag, just checking the beginning so do it

        return (!empty($inside_tag));
    }

    /**
     * Get post IDs from certain category
     *
     * @param $category_id
     * @return array
     */
    public static function getCategoryPosts($category_id)
    {
        global $wpdb;

        $posts = [];
        $categories = $wpdb->get_results("SELECT r.object_id as `id` FROM {$wpdb->term_relationships} r INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id WHERE tt.term_id = " . $category_id);
        foreach ($categories as $post) {
            $posts[] = $post->id;
        }

        return $posts;
    }

    /**
     * Run function for all editors
     *
     * @param $action
     * @param $params
     */
    public static function editors($action, $params)
    {
        $editors = [
            'Beaver',
            'Elementor',
            'Origin',
            'Oxygen',
            'Thrive',
            'Themify',
            'Muffin',
            'Enfold',
            'Cornerstone',
            'WPRecipe',
            'Goodlayers'
        ];

        foreach ($editors as $editor) {
            $class = 'Wpil_Editor_' . $editor;
            call_user_func_array([$class, $action], $params);
        }
    }

    /**
     * TODO: Fill out so that we can pull the editors that are actually active and run through them.
     */
    public static function get_active_editors(){
        $editors = array();
    }

    /**
     * Makes sure all single and double qoutes are excaped once in the supplied text.
     * @param string $text The text that needs to have it's quotes escaped
     * @return string $text The updated text with the single and double qoutes escaped
     **/
    public static function normalize_slashes($text){
        // add slashes to the single qoutes
        $text = mb_eregi_replace("(?<!\\\\)'", "\'", $text);
        // add slashes to the double qoutes
        $text = mb_eregi_replace('(?<!\\\\)"', '\"', $text);
        // and return the text
        return $text;
    }

    /**
     * Checks to see if the link is inside a JSON element and adds appropriate escaping for it.
     * @param string $content The post content that we're processing.
     * @param string $changed_sentence The sentence that contains the link that we might be escaping.
     * @param int $position_start The starting character position of the SENTENCE that we'll be replacing. (Sentence contains the link)
     * @param int $position_start The final character position of the SENTENCE that we'll be replacing. (Sentence contains the link)
     **/
    public static function processLinkForJsonContent($content, $changed_sentence, $position_start = 0, $position_end = 0){
        // if Block Lab is active
        if(class_exists('Block_Lab\\Component_Abstract')){
            // check if the content is inside a block
            $len = strlen($content);
            $block_start = strrpos($content, '<!-- wp:block-lab', ($position_start - $len));
            $block_end = strrpos($content, '/-->', ($position_start - $len));

            // if the replace is inside a block, the opening block tag will be closer than the closing tag of whatever block came before.
            if($block_start > $block_end){
                // return double slashed content for the block labs block
                return addslashes(addslashes($changed_sentence));
            }
        }

        // todo continue work on a later date
        if(false){
            preg_match_all('#(?:<!-- wp:[a-zA-Z\/_\-1-9]*? )({(?:.*?)})(?: \/-->)#u', $content, $matches, PREG_OFFSET_CAPTURE);

            if(isset($matches[1]) && !empty($matches[1])){
                foreach($matches[1] as $match){

                    // if the changed sentence is within a json data object
                    if($match[1] < $position_start && ($match[1] + mb_strlen($match[0]) > $position_end)){
                        // try to detect any pre-existing html tags so we can get an idea of the formatting used

                        // regex to pull out html tags. Check over any tags and see how their content is encoded for storage so we can tell how to handle it
                        /***
                         * (<([a-z])*? ).*?>(.*?)<\/\2>|(\\u003c([a-z])*? ).*?\\u003e(.*?)\\u003c\/\5\\u003e 
                         *
                         ****/



                        // return true
                        return true;
                    }elseif($match[1] > $position_end){
                        // if we're searching objects beyond the sentence, exit
                        break;
                    }
                }
            }
        }

        // by default, content is not json.
        return $changed_sentence;
    }

    /**
     * Gets the most recent revision id for the current post.
     * @param int|string|$post The id or post object that we want to get the revsions for
     * @return int|false The id of the most recent revsion or false if we couldn't find it.
     **/
    public static function get_most_recent_revision_id($post_id = 0){
        if(empty($post_id)){
            return false;
        }

        $revisions = wp_get_post_revisions($post_id);

        if(empty($revisions) || !is_array($revisions)){
            return false;
        }

        $latest = 0;
        foreach($revisions as $revision){
            if($latest < $revision->ID){
                $latest = $revision->ID;
            }
        }

        return $latest;
    }

    /**
     * Checks to see if the recently saved Divi content without links is the same as the Divi content submitted with the edit form.
     * What happens normally is we save the links before Divi has a chance to save it's content.
     * Then after Divi saves it's content, it checks to see if the 
     **/
    public static function verify_divi_save_status($content_saved){
        global $wpdb;

        if($content_saved){
            return $content_saved;
        }

        $post_id = absint( $_POST['post_id'] );
        $saved_post = get_post( $post_id );
        $current_gmt = current_time('mysql', true);

        // if it's been longer than 5 minutes since the post was last updated
        if( empty($saved_post) ||
            !isset($saved_post->post_modified_gmt) ||
            abs(strtotime($saved_post->post_modified_gmt) - strtotime($current_gmt)) > 300)
        {
            // return the current state of content savedness because it's unlikely that the post has been updated
            return $content_saved;
        }

        $layout_type = isset( $_POST['layout_type'] ) ? sanitize_text_field( $_POST['layout_type'] ) : '';
        $shortcode_data = json_decode( stripslashes( $_POST['modules'] ), true );
        $post_content = et_fb_process_to_shortcode( $shortcode_data, $_POST['options'], $layout_type );
        $sanitized_content = sanitize_post_field( 'post_content', $post_content, $post_id, 'db' );

		$saved_post_content   = $saved_post->post_content;
		$builder_post_content = stripslashes( $sanitized_content );

		if ( 'utf8' === $wpdb->get_col_charset( $wpdb->posts, 'post_content' ) ) {
			$builder_post_content = wp_encode_emoji( $builder_post_content );
		}

        $unlinked_saved_post_content = Wpil_Link::remove_all_links_from_text($saved_post_content);
        $unlinked_builder_post_content = Wpil_Link::remove_all_links_from_text($builder_post_content);

        $saved_verification = $unlinked_saved_post_content === $unlinked_builder_post_content;

        return $saved_verification;
    }

    /**
     * Checks to make sure that the given post's type is one that we've created special support for.
     * Returns the post's type if it is, and false if it's not
     * @param WP_Post|Wpil_Model_Post|Array of WP post data
     * @return string|bool Post name if it's one that we have special support for. False if we don't
     **/
    public static function get_post_processing_type($post){
        if(empty($post)){
            return false;
        }
        $type = '';

        if(is_a($post, 'WP_Post') && isset($post->post_type)){
            $type = $post->post_type;
        }elseif(is_a($post, 'Wpil_Model_Post') && $post->type === 'post'){
            $type = get_post_type($post->id);
        }elseif(is_array($post) && isset($post['post_type']) && !empty($post['post_type'])){
            $type = $post['post_type'];
        }

        if(empty($type)){
            return false;
        }

        // now go over a list of the post types that we've built support for.
        // If one of their identifying constants or classes is active, we know we're not dealing with a duplicate named PT
        switch($type){
            case 'web-story':
                if(defined('WEBSTORIES_VERSION')){
                    return $type;
                }
            break;
            default:

            break;
        }

        return false;
    }

    /**
     * Helper function for checking if the supplied string is json
     * @return bool
     **/
    public static function is_json($str) {
        if(empty($str) || !is_string($str)){
            return false;
        }
        $json = json_decode($str);
        return $json && $str != $json;
    }
}
