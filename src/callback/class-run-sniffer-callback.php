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

use \PHP_CodeSniffer\Runner;
use \PHP_CodeSniffer\Config;
use \PHP_CodeSniffer\Reporter;
use \PHP_CodeSniffer\Files\DummyFile;
use \WordPressCS\WordPress\PHPCSHelper;

use Theme_Sniffer\Sniffs\Readme\Validator as Readme;
use Theme_Sniffer\Sniffs\Screenshot\Validator as Screenshot;

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
	 * The warning key value
	 *
	 * @var string
	 */
	const WARNING = 'warning';

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
	 * Readme file name
	 *
	 * @var string
	 */
	const README = 'readme.txt';

	/**
	 * Theme's slug
	 *
	 * @var string
	 */
	private static $theme_slug;

	/**
	 * Theme's root
	 *
	 * @var string
	 */
	private static $theme_root;

	/**
	 * Callback method of the current class.
	 *
	 * @throws \PHP_CodeSniffer\Exceptions\DeepExitException Sniffer error.
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

		self::$theme_slug = sanitize_text_field( wp_unslash( $_POST[ self::THEME_NAME ] ) );
		self::$theme_root = get_theme_root( self::$theme_slug );

		$show_warnings = true;

		if ( isset( $_POST[ self::HIDE_WARNING ] ) && $_POST[ self::HIDE_WARNING ] === 'true' ) {
			$show_warnings = '0';
		}

		$raw_output = false;

		if ( isset( $_POST[ self::RAW_OUTPUT ] ) && $_POST[ self::RAW_OUTPUT ] === 'true' ) {
			$raw_output = true;
		}

		$ignore_annotations = true;

		if ( isset( $_POST[ self::IGNORE_ANNOTATIONS ] ) && $_POST[ self::IGNORE_ANNOTATIONS ] === 'true' ) {
			$ignore_annotations = false;
		}

		$minimum_php_version = '5.6';

		if ( isset( $_POST[ self::MINIMUM_PHP_VERSION ] ) && ! empty( $_POST[ self::MINIMUM_PHP_VERSION ] ) ) {
			$minimum_php_version = sanitize_text_field( wp_unslash( $_POST[ self::MINIMUM_PHP_VERSION ] ) );
		}

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

		$args = [];

		// Current theme text domain.
		$args[ self::TEXT_DOMAINS ] = [ self::$theme_slug ];

		$all_files = [ 'php' ];

		$theme     = wp_get_theme( self::$theme_slug );
		$all_files = $theme->get_files( $all_files, -1, false );

		// Disallowed folders.
		$re = '/(\/node_modules)|(\/vendor)|(\/test)|(\/tests)/';

		$files_to_sniff = [];

		foreach ( $all_files as $file_name => $file_path ) {

			// Check for Frameworks.
			$allowed_frameworks = [
				'kirki'       => 'kirki.php',
				'hybrid-core' => 'hybrid.php',
			];

			foreach ( $allowed_frameworks as $framework_textdomain => $identifier ) {
				if ( strrpos( $file_name, $identifier ) !== false && ! in_array( $framework_textdomain, $args[ self::TEXT_DOMAINS ], true ) ) {
					$args[ self::TEXT_DOMAINS ][] = $framework_textdomain;
				}
			}

			// Check if files are in disallowed folders (defined by the regex) and remove them.
			preg_match_all( $re, $file_path, $matches, PREG_SET_ORDER, 0 );

			if ( empty( $matches ) ) {
				$files_to_sniff[ $file_name ] = $file_path;
			}
		}

		// Safeguard, but don't seems to be working correctly.
		$ignored = '.*/node_modules/.*,.*/vendor/.*,.*/assets/build/.*,.*/build/.*,.*/bin/.*,.*/tests/.*,.*/test/.*';

		$results_arguments = [
			'extensions'          => 'php',
			'show_warnings'       => $show_warnings,
			'minimum_php_version' => $minimum_php_version,
			'args'                => $args,
			'theme_prefixes'      => $theme_prefixes,
			'all_files'           => $files_to_sniff,
			'standards_array'     => $standards_array,
			'ignore_annotations'  => $ignore_annotations,
			'ignored'             => $ignored,
			'raw_output'          => $raw_output,
		];

		$sniff_results = $this->get_sniff_results( $results_arguments );

		if ( $raw_output ) {
			$results_raw = [
				self::SUCCESS => true,
				self::DATA    => $sniff_results,
			];

			\wp_send_json( $results_raw, 200 );
		}

		$sniffer_results = json_decode( $sniff_results, true );

		$total_errors  = $sniffer_results[ self::TOTALS ][ self::ERRORS ];
		$total_warning = $sniffer_results[ self::TOTALS ][ self::WARNINGS ];
		$total_fixable = $sniffer_results[ self::TOTALS ][ self::FIXABLE ];
		$total_files   = $sniffer_results[ self::FILES ];

		// Check theme headers.
		$theme_header_checks = $this->style_headers_check( self::$theme_slug, $theme, $show_warnings );
		$screenshot_checks   = $this->screenshot_check();
		$readme_checks       = $this->readme_check();

		foreach ( $screenshot_checks as $file ) {
			$total_errors  += $file[ self::ERRORS ];
			$total_warning += $file[ self::WARNINGS ];
		}

		$total_files += $screenshot_checks;

		foreach ( $readme_checks as $file ) {
			$total_errors  += $file[ self::ERRORS ];
			$total_warning += $file[ self::WARNINGS ];
		}

		$total_files += $readme_checks;

		$total_errors  += $theme_header_checks[ self::TOTALS ][ self::ERRORS ];
		$total_warning += $theme_header_checks[ self::TOTALS ][ self::WARNINGS ];
		$total_fixable += $theme_header_checks[ self::TOTALS ][ self::FIXABLE ];
		$total_files   += $theme_header_checks[ self::FILES ];

		// Filtering the files for easier JS handling.
		$file_i = 0;
		$files  = [];

		foreach ( $total_files as $file_path => $file_sniff_results ) {

			// Allow the file list to pass any .js through for further handling, and remove all others with no errors or warnings.
			if ( substr( $file_path, -3 ) !== '.js' && ( $file_sniff_results[ self::ERRORS ] === 0 && $file_sniff_results[ self::WARNINGS ] === 0 ) ) {
				continue;
			}

			$files[ $file_i ][ self::FILE_PATH ] = $file_path;
			$files[ $file_i ][ self::ERRORS ]    = $file_sniff_results[ self::ERRORS ];
			$files[ $file_i ][ self::WARNINGS ]  = $file_sniff_results[ self::WARNINGS ];
			$files[ $file_i ][ self::MESSAGES ]  = $file_sniff_results[ self::MESSAGES ];
			$file_i++;
		}

		$results = [
			self::SUCCESS => true,
			self::TOTALS  => [
				self::ERRORS   => $total_errors,
				self::WARNINGS => $total_warning,
				self::FIXABLE  => $total_fixable,
			],
			self::FILES   => $files,
		];

		\wp_send_json( $results, 200 );
	}

	/**
	 * Method that returns the results based on a custom PHPCS Runner
	 *
	 * @param array ...$arguments Array of passed arguments.
	 * @return string             Sniff results string.
	 *
	 * @throws \PHP_CodeSniffer\Exceptions\DeepExitException Sniffer exception.
	 */
	protected function get_sniff_results( ...$arguments ) {
		// Unpack the arguments.
		$show_warnings       = $arguments[0]['show_warnings'];
		$minimum_php_version = $arguments[0]['minimum_php_version'];
		$args                = $arguments[0]['args'];
		$theme_prefixes      = $arguments[0]['theme_prefixes'];
		$extensions          = $arguments[0]['extensions'];
		$all_files           = $arguments[0]['all_files'];
		$standards_array     = $arguments[0]['standards_array'];
		$ignore_annotations  = $arguments[0]['ignore_annotations'];
		$ignored             = $arguments[0]['ignored'];
		$raw_output          = $arguments[0]['raw_output'];

		// Create a custom runner.
		$runner = new Runner();

		$config_args = [ '-s', '-p' ];

		if ( $show_warnings === '0' ) {
			$config_args[] = '-n';
		}

		$runner->config = new Config( $config_args );

		$all_files = array_values( $all_files );

		if ( $extensions ) {
			$runner->config->extensions = [
				'php' => 'PHP',
				'inc' => 'PHP',
			];
		}

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

		// Set default standard.
		PHPCSHelper::set_config_data( self::DEFAULT_STANDARD, $this->get_default_standard(), true );

		// Ignoring warnings when generating the exit code.
		PHPCSHelper::set_config_data( self::IGNORE_WARNINGS_ON_EXIT, true, true );

		// Set minimum supported PHP version.
		PHPCSHelper::set_config_data( self::TEST_VERSION, $minimum_php_version . '-', true );

		// Set text domains.
		PHPCSHelper::set_config_data( self::TEXT_DOMAIN, implode( ',', $args[ self::TEXT_DOMAINS ] ), true );

		if ( $theme_prefixes !== '' ) {
			// Set prefix.
			PHPCSHelper::set_config_data( self::PREFIXES, $theme_prefixes, true );
		}

		$runner->reporter = new Reporter( $runner->config );

		foreach ( $all_files as $file_path ) {
			$file       = new DummyFile( file_get_contents( $file_path ), $runner->ruleset, $runner->config );
			$file->path = $file_path;

			$runner->processFile( $file );
		}

		ob_start();
		$runner->reporter->printReports();
		$report = ob_get_clean();

		return $report;
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
	protected function style_headers_check( $theme_slug, \WP_Theme $theme, $show_warnings ) {
		$required_headers = $this->get_required_headers();

		$notices = [];

		foreach ( $required_headers as $header ) {
			if ( $theme->get( $header ) ) {
				continue;
			}

			$notices[] = [
				self::MESSAGE  => sprintf(
					/* translators: 1: comment header line */
					esc_html__( 'The %1$s is not defined in the style.css header.', 'theme-sniffer' ),
					$header
				),
				self::SEVERITY => self::ERROR,
			];
		}

		if ( strpos( $theme_slug, 'wordpress' ) || strpos( $theme_slug, 'theme' ) ) { // phpcs:ignore
			$notices[] = [
				self::MESSAGE  => esc_html__( 'The theme name cannot contain WordPress or Theme as a part of its name.', 'theme-sniffer' ),
				self::SEVERITY => self::ERROR,
			];
		}

		if ( preg_match( '|[^\d\.]|', $theme->get( 'Version' ) ) ) {
			$notices[] = [
				self::MESSAGE  => esc_html__( 'Version strings can only contain numeric and period characters (e.g. 1.2).', 'theme-sniffer' ),
				self::SEVERITY => self::ERROR,
			];
		}

		// Prevent duplicate URLs.
		$themeuri  = trim( $theme->get( 'ThemeURI' ), '/\\' );
		$authoruri = trim( $theme->get( 'AuthorURI' ), '/\\' );

		if ( ( $themeuri === $authoruri ) && ( ! empty( $themeuri ) || ! empty( $authoruri ) ) ) {
			$notices[] = [
				self::MESSAGE  => esc_html__( 'Duplicate theme and author URLs. A theme URL is a page/site that provides details about this specific theme. An author URL is a page/site that provides information about the author of the theme. The theme and author URL are optional.', 'theme-sniffer' ),
				self::SEVERITY => self::ERROR,
			];
		}

		if ( $theme_slug === $theme->get( 'Text Domain' ) ) {
			$notices[] = [
				self::MESSAGE  => sprintf(
					/* translators: %1$s: Text Domain, %2$s: Theme Slug */
					esc_html__( 'The text domain "%1$s" must match the theme slug "%2$s".', 'theme-sniffer' ),
					$theme->get( 'TextDomain' ),
					$theme_slug
				),
				self::SEVERITY => self::ERROR,
			];
		}

		$registered_tags    = $this->get_theme_tags();
		$tags               = array_map( 'strtolower', $theme->get( 'Tags' ) );
		$tags_count         = array_count_values( $tags );
		$subject_tags_names = [];

		$subject_tags = array_flip( $registered_tags['subject_tags'] );
		$allowed_tags = array_flip( $registered_tags['allowed_tags'] );

		foreach ( $tags as $tag ) {
			if ( $tags_count[ $tag ] > 1 ) {
				$notices[] = [
					self::MESSAGE  => sprintf(
						/* translators: %s: Theme tag */
						esc_html__( 'The tag "%s" is being used more than once, please remove the duplicate.', 'theme-sniffer' ),
						$tag
					),
					self::SEVERITY => self::ERROR,
				];
			}

			if ( isset( $subject_tags[ $tag ] ) ) {
				$subject_tags_names[] = $tag;
				continue;
			}

			if ( ! isset( $allowed_tags[ $tag ] ) ) {
				$notices[] = [
					self::MESSAGE  => sprintf(
						/* translators: %s: Theme tag */
						wp_kses_post( __( 'Please remove "%s" as it is not a standard tag.', 'theme-sniffer' ) ),
						$tag
					),
					self::SEVERITY => self::ERROR,
				];
				continue;
			}

			if ( 'accessibility-ready' === $tag && $show_warnings !== '0' ) {
				$notices[] = [
					self::MESSAGE  => wp_kses_post( __( 'Themes that use the "accessibility-ready" tag will need to undergo an accessibility review.', 'theme-sniffer' ) ),
					self::SEVERITY => self::WARNING,
				];
			}
		}

		$subject_tags_count = count( $subject_tags_names );

		if ( $subject_tags_count > 3 ) {
			$notices[] = [
				self::MESSAGE  => sprintf(
					/* translators: 1: Subject theme tag, 2: Tags list */
					esc_html__( 'A maximum of 3 subject tags are allowed. The theme has %1$d subjects tags [%2$s]. Please remove the subject tags, which do not directly apply to the theme.', 'theme-sniffer' ),
					$subject_tags_count,
					implode( ',', $subject_tags_names )
				),
				self::SEVERITY => self::ERROR,
			];
		}

		$error_count   = 0;
		$warning_count = 0;
		$messages      = [];

		foreach ( $notices as $notice ) {
			$severity = $notice[ self::SEVERITY ];

			if ( $severity === self::ERROR ) {
				$error_count++;
			} else {
				$warning_count++;
			}

			$messages[] = [
				self::MESSAGE  => $notice[ self::MESSAGE ],
				self::SEVERITY => $severity,
				self::FIXABLE  => false,
				self::TYPE     => strtoupper( $severity ),
			];
		}

		$header_results = [
			self::TOTALS => [
				self::ERRORS   => $error_count,
				self::WARNINGS => $warning_count,
				self::FIXABLE  => $error_count + $warning_count,
			],
			self::FILES => [
				self::$theme_root . "/{$theme_slug}/style.css" => [
					self::ERRORS   => $error_count,
					self::WARNINGS => $warning_count,
					self::MESSAGES => $messages,
				],
			],
		];

		return $header_results;
	}

	/**
	 * Performs readme.txt sniffs.
	 *
	 * @since 1.1.0
	 *
	 * @return array $check Sniffer file report.
	 */
	protected function readme_check() {
		$validator = new Readme( self::$theme_slug );
		return $validator->get_results();
	}

	/**
	 * Perform screenshot sniffs.
	 *
	 * @since 1.0.0
	 */
	protected function screenshot_check() {
		$validator = new Screenshot( self::$theme_slug );
		return $validator->get_results();
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
}
