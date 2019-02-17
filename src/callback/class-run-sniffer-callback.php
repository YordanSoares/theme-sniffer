<?php
/**
 * File that holds class for documentation order ajax callback
 *
 * @since 1.0.0
 * @package Theme_Sniffer\Callback
 */

declare( strict_types=1 );

namespace Theme_Sniffer\Callback;

// We need to use the PHP_CS autoloader to access the Runner and Config.
require_once dirname( __FILE__, 3 ) . '/vendor/squizlabs/php_codesniffer/autoload.php';
require_once dirname( __FILE__, 3 ) . '/vendor/wp-coding-standards/wpcs/WordPress/PHPCSHelper.php';

use \PHP_CodeSniffer\Runner;
use \PHP_CodeSniffer\Config;
use \PHP_CodeSniffer\Reporter;
use \PHP_CodeSniffer\Files\DummyFile;
use \WordPress\PHPCSHelper;

use Theme_Sniffer\Helpers\Sniffer_Helpers;

/**
* Class Run_Sniffer_Callback.
*
* @since 1.0.0
*/
final class Run_Sniffer_Callback extends Base_Ajax_Callback {

	use Sniffer_Helpers;

	/**
	 * Callback name
	 *
	 * @var string
	 */
	const CALLBACK_ACTION = 'run_sniffer';

	/**
	 * Nonce action string
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'theme-sniffer_action';

	/**
	 * Nonce $_POST key
	 *
	 * @var string
	 */
	const NONCE = 'nonce';

	/**
	 * The themeName $_POST key
	 *
	 * @var string
	 */
	const THEME_NAME = 'themeName';

	/**
	 * The themePrefixes $_POST key
	 *
	 * @var string
	 */
	const THEME_PREFIXES = 'themePrefixes';

	/**
	 * The wpRulesets $_POST key
	 *
	 * @var string
	 */
	const WP_RULESETS = 'wpRulesets';

	/**
	 * The hideWarning $_POST key
	 *
	 * @var string
	 */
	const HIDE_WARNING = 'hideWarning';

	/**
	 * The rawOutput $_POST key
	 *
	 * @var string
	 */
	const RAW_OUTPUT = 'rawOutput';

	/**
	 * The ignoreAnnotations $_POST key
	 *
	 * @var string
	 */
	const IGNORE_ANNOTATIONS = 'ignoreAnnotations';

	/**
	 * The minimumPHPVersion $_POST key
	 *
	 * @var string
	 */
	const MINIMUM_PHP_VERSION = 'minimumPHPVersion';

	/**
	 * The text_domains argument key
	 *
	 * @var string
	 */
	const TEXT_DOMAINS = 'text_domains';

	/**
	 * The default_standard config key
	 *
	 * @var string
	 */
	const DEFAULT_STANDARD = 'default_standard';

	/**
	 * The ignore_warnings_on_exit config key
	 *
	 * @var string
	 */
	const IGNORE_WARNINGS_ON_EXIT = 'ignore_warnings_on_exit';

	/**
	 * The show_warnings config key
	 *
	 * @var string
	 */
	const SHOW_WARNINGS = 'show_warnings';

	/**
	 * The testVersion config key
	 *
	 * @var string
	 */
	const TEST_VERSION = 'testVersion';

	/**
	 * The text_domain config key
	 *
	 * @var string
	 */
	const TEXT_DOMAIN = 'text_domain';

	/**
	 * The prefixes config key
	 *
	 * @var string
	 */
	const PREFIXES = 'prefixes';

	/**
	 * The totals key value
	 *
	 * @var string
	 */
	const TOTALS = 'totals';

	/**
	 * The errors key value
	 *
	 * @var string
	 */
	const ERRORS = 'errors';

	/**
	 * The warnings key value
	 *
	 * @var string
	 */
	const WARNINGS = 'warnings';

	/**
	 * The fixable key value
	 *
	 * @var string
	 */
	const FIXABLE = 'fixable';

	/**
	 * The files key value
	 *
	 * @var string
	 */
	const FILES = 'files';

	/**
	 * The success key value
	 *
	 * @var string
	 */
	const SUCCESS = 'success';

	/**
	 * The data key value
	 *
	 * @var string
	 */
	const DATA = 'data';

	/**
	 * The messages key value
	 *
	 * @var string
	 */
	const MESSAGES = 'messages';

	/**
	 * The filePath key value
	 *
	 * @var string
	 */
	const FILE_PATH = 'filePath';

	/**
	 * The message key value
	 *
	 * @var string
	 */
	const MESSAGE = 'message';

	/**
	 * The severity key value
	 *
	 * @var string
	 */
	const SEVERITY = 'severity';

	/**
	 * The error key value
	 *
	 * @var string
	 */
	const ERROR = 'error';

	/**
	 * The type key value
	 *
	 * @var string
	 */
	const TYPE = 'type';

	/**
	* Callback privacy
	*
	* @var bool
	*/
	const CB_PUBLIC = false;

	/**
	 * Callback method of the current class.
	 */
	public function callback() {
		// Permissions check.
		if ( ! current_user_can( 'create_users' ) ) {
			$message = esc_html__( 'You don\'t have high enough permission.', 'theme-sniffer' );
			$error   = new \WP_Error( '403', $message );
			wp_send_json_error( $error );
		}

		// Nonce check.
		if ( ! isset( $_POST[ self::NONCE ] ) ||
			! \wp_verify_nonce( \sanitize_key( $_POST[ self::NONCE ] ), self::NONCE_ACTION )
		) {
			$message = esc_html__( 'Nonce error.', 'theme-sniffer' );
			$error   = new \WP_Error( '400', $message );
			wp_send_json_error( $error );
		}

		// Bail if theme wasn't selected.
		if ( empty( $_POST[ self::THEME_NAME ] ) ) {
			$message = esc_html__( 'Theme name not selected.', 'theme-sniffer' );
			$error   = new \WP_Error( '404', $message );
			wp_send_json_error( $error );
		}

		// Additional settings (theme prefixes, standards, preview options).
		$theme_prefixes = '';

		if ( isset( $_POST[ self::THEME_PREFIXES ] ) && $_POST[ self::THEME_PREFIXES ] !== '' ) {
			$theme_prefixes = sanitize_text_field( wp_unslash( $_POST[ self::THEME_PREFIXES ] ) );
		}

		if ( empty( $_POST[ self::WP_RULESETS ] ) ) {
			$message = esc_html__( 'Please select at least one standard.', 'theme-sniffer' );
			$error   = new \WP_Error( '404', $message );
			wp_send_json_error( $error );
		}

		$theme_slug = sanitize_text_field( wp_unslash( $_POST[ self::THEME_NAME ] ) );

		$show_warnings = true;

		if ( isset( $_POST[ self::HIDE_WARNING ] ) && $_POST[ self::HIDE_WARNING ] === 'true' ) {
			$show_warnings = '0';
		}

		$raw_output = false;

		if ( isset( $_POST[ self::RAW_OUTPUT ] ) && $_POST[ self::RAW_OUTPUT ] === 'true' ) {
			$raw_output = true;
		}

		$ignore_annotations = false;

		if ( isset( $_POST[ self::IGNORE_ANNOTATIONS ] ) && $_POST[ self::IGNORE_ANNOTATIONS ] === 'true' ) {
			$ignore_annotations = true;
		}

		if ( isset( $_POST[ self::MINIMUM_PHP_VERSION ] ) && ! empty( $_POST[ self::MINIMUM_PHP_VERSION ] ) ) {
			$minimum_php_version = sanitize_text_field( wp_unslash( $_POST[ self::MINIMUM_PHP_VERSION ] ) );
		}

		// Check theme headers.
		$theme_header_checks = $this->style_headers_check( $theme_slug, wp_get_theme( $theme_slug ), $show_warnings );

		// Take standards from the trait.
		$standards = $this->get_wpcs_standards();

		$selected_standards = array_map(
			'sanitize_text_field',
			wp_unslash( $_POST[ self::WP_RULESETS ] )
		);

		$standards_array = array_map(
			function( $standard ) use ( $standards ) {
				if ( ! empty( $standards[ $standard ] ) ) {
					return $standards[ $standard ]['label'];
				}
			},
			$selected_standards
		);

		$theme     = \wp_get_theme( $theme_slug );
		$php_files = $theme->get_files( 'php', 4, false );

		// Current theme text domain.
		$args[ self::TEXT_DOMAINS ][] = $theme_slug;

		// Frameworks.
		foreach ( $php_files as $key => $file ) {
			if ( strrpos( $key, 'hybrid.php' ) ) {
				$args[ self::TEXT_DOMAINS ][] = 'hybrid-core';
			}
			if ( strrpos( $key, 'kirki.php' ) ) {
				$args[ self::TEXT_DOMAINS ][] = 'kirki';
			}
		}

		$all_files     = $theme->get_files( array( 'php', 'css,', 'js' ), -1, false );
		$removed_files = [];

		/**
		 * Check if a file is minified.
		 * Loops through all the files and checks if it's minified. If it is, skip it
		 * because it will break phpcs.
		 */
		foreach ( $all_files as $file_name => $file_path ) {
			// Check if files have .min in the file name.
			if ( false !== strpos( $file_name, '.min.js' ) || false !== strpos( $file_name, '.min.css' ) ) {
				unset( $all_files[ $file_name ] );
				$removed_files[] = $file_name;
				break;
			}

			try {
				$file_contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$file_lines    = explode( "\n", $file_contents );

				$row = 0;
				foreach ( $file_lines as $line ) {
					if ( $row <= 10 ) {
						if ( strlen( $line ) > 1000 ) {
							unset( $all_files[ $file_name ] );
							$removed_files[] = $file_name;
							break;
						}
					}
				}
			} catch ( Exception $e ) {
				new \WP_Error(
					'error_reading_file',
					sprintf(
						/* translators: %s: Name of the file */
						esc_html__( 'There was an error reading the file %s', 'theme-sniffer' ),
						$file_name
					)
				);
			}
		}

		$ignored = '.*/node_modules/.*,.*/vendor/.*,.*/assets/build/.*,.*/build/.*,.*/bin/.*';

		// Create a custom runner.
		$runner = new Runner();

		$runner->config = new Config( [ '-s', '-p' ] );

		// Set default standard.
		PHPCSHelper::set_config_data( self::DEFAULT_STANDARD, $this->get_default_standard(), true );

		// Ignoring warnings when generating the exit code.
		PHPCSHelper::set_config_data( self::IGNORE_WARNINGS_ON_EXIT, true, true );

		// Show only errors?
		PHPCSHelper::set_config_data( self::SHOW_WARNINGS, $show_warnings, true );

		// Set minimum supported PHP version.
		PHPCSHelper::set_config_data( self::TEST_VERSION, $minimum_php_version . '-', true );

		// Set text domains.
		PHPCSHelper::set_config_data( self::TEXT_DOMAIN, implode( ',', $args[ self::TEXT_DOMAINS ] ), true );

		if ( $theme_prefixes !== '' ) {
			// Set prefix.
			PHPCSHelper::set_config_data( self::PREFIXES, $theme_prefixes, true );
		}

		$all_files = array_values( $all_files );

		$runner->config->standards   = $standards_array;
		$runner->config->files       = $all_files;
		$runner->config->annotations = $ignore_annotations;
		$runner->config->parallel    = 8;
		$runner->config->colors      = false;
		$runner->config->tabWidth    = 0;
		$runner->config->reportWidth = 110;
		$runner->config->interactive = false;
		$runner->config->cache       = false;
		$runner->config->ignored     = $ignored;

		if ( ! $raw_output ) {
			$runner->config->reports = [ 'json' => null ];
		}

		$runner->init();

		$runner->reporter = new Reporter( $runner->config );

		foreach ( $all_files as $file_path ) {
			$file       = new DummyFile( file_get_contents( $file_path ), $runner->ruleset, $runner->config );
			$file->path = $file_path;

			$runner->processFile( $file );
		}

		ob_start();
		$runner->reporter->printReports();
		$sniff_results = ob_get_clean();

		if ( $raw_output ) {
			$results_raw = array(
				self::SUCCESS => true,
				self::DATA    => $sniff_results,
			);

			\wp_send_json( $results_raw, 200 );
		}

		$theme_header_checks;

		$sniffer_results = json_decode( $sniff_results, true );

		$total_errors  = $theme_header_checks[ self::TOTALS ][ self::ERRORS ] + $sniffer_results[ self::TOTALS ][ self::ERRORS ];
		$total_warning = $theme_header_checks[ self::TOTALS ][ self::WARNINGS ] + $sniffer_results[ self::TOTALS ][ self::WARNINGS ];
		$total_fixable = $theme_header_checks[ self::TOTALS ][ self::FIXABLE ] + $sniffer_results[ self::TOTALS ][ self::FIXABLE ];
		$total_files   = $theme_header_checks[ self::FILES ] + $sniffer_results[ self::FILES ];

		// Filtering the files for easier JS handling.
		$file_i = 0;
		foreach ( $total_files as $file_path => $file_sniff_results ) {
			if ( $file_sniff_results[ self::ERRORS ] === 0 && $file_sniff_results[ self::WARNINGS ] === 0 ) {
				continue;
			}

			$files[ $file_i ][ self::FILE_PATH ] = $file_path;
			$files[ $file_i ][ self::ERRORS ]    = $file_sniff_results[ self::ERRORS ];
			$files[ $file_i ][ self::WARNINGS ]  = $file_sniff_results[ self::WARNINGS ];
			$files[ $file_i ][ self::MESSAGES ]  = $file_sniff_results[ self::MESSAGES ];
			$file_i++;
		}

		$results = array(
			self::SUCCESS => true,
			self::TOTALS  => array(
				self::ERRORS   => $total_errors,
				self::WARNINGS => $total_warning,
				self::FIXABLE  => $total_fixable,
			),
			self::FILES   => $files,
		);

		\wp_send_json( $results, 200 );
	}

	/**
	 * Returns true if the callback should be public
	 *
	 * @return boolean true if callback is public.
	 */
	protected function is_public() : bool {
		return self::CB_PUBLIC;
	}

	/**
	 * Get name of the callback action
	 *
	 * @return string Name of the callback action.
	 */
	protected function get_action_name() : string {
		return self::CALLBACK_ACTION;
	}

	/**
	 * Perform style.css header check.
	 *
	 * @since 0.3.0
	 *
	 * @param string    $theme_slug    Theme slug.
	 * @param \WP_Theme $theme         WP_Theme Theme object.
	 * @param bool      $show_warnings Show warnings.
	 *
	 * @return bool
	 */
	private function style_headers_check( $theme_slug, \WP_Theme $theme, $show_warnings ) {
		$required_headers = array(
			'Name',
			'Description',
			'Author',
			'Version',
			'License',
			'License URI',
			'TextDomain',
		);

		foreach ( $required_headers as $header ) {
			if ( $theme->get( $header ) ) {
				continue;
			}

			$notices[] = array(
				self::MESSAGE  => sprintf(
					/* translators: 1: comment header line */
					esc_html__( 'The %1$s is not defined in the style.css header.', 'theme-sniffer' ),
					$header
				),
				self::SEVERITY => self::ERROR,
			);
		}

		if ( strpos( $theme_slug, 'wordpress' ) || strpos( $theme_slug, 'theme' ) ) { // WPCS: spelling ok.
			$notices[] = array(
				self::MESSAGE  => esc_html__( 'The theme name cannot contain WordPress or Theme as a part of its name.', 'theme-sniffer' ),
				self::SEVERITY => self::ERROR,
			);
		}

		if ( preg_match( '|[^\d\.]|', $theme->get( 'Version' ) ) ) {
			$notices[] = array(
				self::MESSAGE  => esc_html__( 'Version strings can only contain numeric and period characters (e.g. 1.2).', 'theme-sniffer' ),
				self::SEVERITY => self::ERROR,
			);
		}

		// Prevent duplicate URLs.
		$themeuri  = trim( $theme->get( 'ThemeURI' ), '/\\' );
		$authoruri = trim( $theme->get( 'AuthorURI' ), '/\\' );

		if ( $themeuri === $authoruri ) {
			$notices[] = array(
				self::MESSAGE  => esc_html__( 'Duplicate theme and author URLs. A theme URL is a page/site that provides details about this specific theme. An author URL is a page/site that provides information about the author of the theme. The theme and author URL are optional.', 'theme-sniffer' ),
				self::SEVERITY => self::ERROR,
			);
		}

		if ( $theme_slug === $theme->get( 'Text Domain' ) ) {
			$notices[] = array(
				self::MESSAGE  => sprintf(
					/* translators: %1$s: Text Domain, %2$s: Theme Slug */
					esc_html__( 'The text domain "%1$s" must match the theme slug "%2$s".', 'theme-sniffer' ),
					$theme->get( 'TextDomain' ),
					$theme_slug
				),
				self::SEVERITY => self::ERROR,
			);
		}

		$registered_tags    = $this->get_theme_tags();
		$tags               = array_map( 'strtolower', $theme->get( 'Tags' ) );
		$tags_count         = array_count_values( $tags );
		$subject_tags_names = array();

		$subject_tags = array_flip( $registered_tags['subject_tags'] );
		$allowed_tags = array_flip( $registered_tags['allowed_tags'] );

		foreach ( $tags as $tag ) {
			if ( $tags_count[ $tag ] > 1 ) {
				$notices[] = array(
					self::MESSAGE  => sprintf(
						/* translators: %s: Theme tag */
						esc_html__( 'The tag "%s" is being used more than once, please remove the duplicate.', 'theme-sniffer' ),
						$tag
					),
					self::SEVERITY => self::ERROR,
				);
			}

			if ( isset( $subject_tags[ $tag ] ) ) {
				$subject_tags_names[] = $tag;
				continue;
			}

			if ( ! isset( $allowed_tags[ $tag ] ) ) {
				$notices[] = array(
					self::MESSAGE  => sprintf(
						/* translators: %s: Theme tag */
						wp_kses_post( __( 'Please remove "%s" as it is not a standard tag.', 'theme-sniffer' ) ),
						$tag
					),
					self::SEVERITY => self::ERROR,
				);
				continue;
			}

			if ( 'accessibility-ready' === $tag && $show_warnings ) {
				$notices[] = array(
					self::MESSAGE  => wp_kses_post( __( 'Themes that use the "accessibility-ready" tag will need to undergo an accessibility review.', 'theme-sniffer' ) ),
					self::SEVERITY => 'warning',
				);
			}
		}

		$subject_tags_count = count( $subject_tags_names );

		if ( $subject_tags_count > 3 ) {
			$notices[] = array(
				self::MESSAGE  => sprintf(
					/* translators: 1: Subject theme tag, 2: Tags list */
					esc_html__( 'A maximum of 3 subject tags are allowed. The theme has %1$d subjects tags [%2$s]. Please remove the subject tags, which do not directly apply to the theme.', 'theme-sniffer' ),
					$subject_tags_count,
					implode( ',', $subject_tags_names )
				),
				self::SEVERITY => self::ERROR,
			);
		}

		$theme_root = get_theme_root( $theme_slug );

		if ( empty( $notices ) ) {
			return array();
		}

		$error_count   = 0;
		$warning_count = 0;
		$messages      = array();

		foreach ( $notices as $notice ) {
			$severity = $notice[ self::SEVERITY ];
			if ( $severity === self::ERROR ) {
				$error_count++;
			} else {
				$warning_count++;
			}

			$messages[] = array(
				self::MESSAGE  => $notice[ self::MESSAGE ],
				self::SEVERITY => $severity,
				self::FIXABLE  => false,
				self::TYPE     => strtoupper( $severity ),
			);
		}

		$header_results = array(
			self::TOTALS => array(
				self::ERRORS   => $error_count,
				self::WARNINGS => $warning_count,
				self::FIXABLE  => $error_count + $warning_count,
			),
			self::FILES => array(
				"{$theme_root}/{$theme_slug}/style.css" => array(
					self::ERRORS   => $error_count,
					self::WARNINGS => $warning_count,
					self::MESSAGES => $messages,
				),
			),
		);

		return $header_results;
	}
}
