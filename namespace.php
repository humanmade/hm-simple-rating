<?php

namespace HM\Simple_Rating;

const NONCE_NAME = 'rate-item-nonce';
const ACTION_NAME = 'rate-item';
const META_KEY = 'rate-item-rating';
const ALLOWED_ITEM_TYPES = [
	'post',
	'term',
];
const SCHEMA = [
	'yes' => 0,
	'no' => 0,
];

/**
 * Start up any necessary functionality.
 *
 * @return void
 */
function bootstrap() {
	add_action( 'widgets_init', __NAMESPACE__ . '\\register' );
	add_action( 'init', __NAMESPACE__ . '\\handle_rating_submission' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_styles' );
	add_action( 'hm_simple_rating_show_count', __NAMESPACE__ . '\\determine_who_always_sees_counts' );
	add_action( 'hm_simple_rating_icon_yes', [ Widget_Rate_Item::class, 'render_yes_icon' ] );
	add_action( 'hm_simple_rating_icon_no', [ Widget_Rate_Item::class, 'render_no_icon' ] );
}

/**
 * Register this widget.
 *
 * @return void
 */
function register() {
	register_widget( Widget_Rate_Item::class );
}

/**
 * Enqueues styles for this plugin.
 *
 * Can be disabled with hm_simple_rating_remove_styles filter.
 *
 * @return void
 */
function enqueue_styles() : void {
	if ( apply_filters( 'hm_simple_rating_remove_styles', false ) ) {
		// This makes it simple for downstream implementations to remove all styles.
		return;
	}

	wp_enqueue_style( 'hm-simple-rating', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/styles/styles.css' );
}

/**
 * For users with the correct permissions, counts always show.
 *
 * @param bool|null $show Whether to show the count.
 *
 * @return bool|null
 */
function determine_who_always_sees_counts( ?bool $show ) : ?bool {
	// Users w/ correct permissions should always see the count.
	if ( current_user_can( get_show_count_capability() ) ) {
		$show = true;
	}

	return $show;
}

/**
 * Return the user capability that will always see counts.
 *
 * @return string
 */
function get_show_count_capability() : string {
	return apply_filters( 'hm_simple_rating_show_count_capability', 'edit_options' );
}

/**
 * Handles potential rating form submissions.
 *
 * @return void
 */
function handle_rating_submission() : void {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		// We have nothing to do in the admin.
		// We aren't currently using AJAX, but let's leave the door open.

		return;
	}

	if ( has_visitor_already_rated() ) {
		// Don't allow people to keep resubmitting by reloading the page.

		return;
	}

	if ( ! maybe_rating_submission() ) {

		return;
	}

	if ( ! verify_submission() ) {
		// Invalid submission, so we don't want to do anything.

		return;
	}

	$submission = get_rating_submission();

	if ( null !== update_rating( $submission ) ) {
		// Set a cookie that expires a year from now, saying that we stored a rating.
		setcookie(
			get_cookie_name(),
			$submission,
			time() + YEAR_IN_SECONDS,
			filter_input( INPUT_POST, '_wp_http_referer' )
		);

		// The cookie won't be available to PHP until the page reloads, so hook this filter to force 'already submitted' during this load.
		add_filter( 'hm_simple_rating_already_submitted', '__return_true' );
	}
}

function get_cookie_name() : string {
	return sprintf( 'rated_%d', get_item_id() );
}

/**
 * Get the type of item this is, so we know how to store meta on it.
 *
 * Value of `invalid` means this isn't a valid type or no type could be
 * determined.
 *
 * @param bool $allow_fallback
 *
 * @return string
 */
function get_item_type( bool $allow_fallback = true ) : string {
	// If the $_POST includes a type key, just use that.
	$type = filter_input( INPUT_POST, 'item_type', FILTER_VALIDATE_REGEXP, [
		'options' => [
			'regexp' => '/' . join( '|', ALLOWED_ITEM_TYPES ) . '/',
			'default' => 'invalid',
		],
	] );
	if ( $type !== 'invalid' || ! $allow_fallback ) {
		return $type;
	}

	// No type was given, so try and guess.
	if ( is_category() || is_tax() ) {
		return 'term';
	}
	if ( is_singular() ) {
		return 'post';
	}

	return 'invalid';
}

/**
 * Get the item ID, so we know where to store meta.
 *
 * Value of 0 means no ID could be found.
 *
 * @param bool $allow_fallback
 *
 * @return int
 */
function get_item_id( bool $allow_fallback = true ) : int {
	// If $_POST includes an id key, just use that.
	$id = filter_input( INPUT_POST, 'item_to_rate', FILTER_VALIDATE_INT, [
		'options' => [
			'default' => 0,
		],
	] );
	if ( $id > 0 || ! $allow_fallback ) {
		return $id;
	}

	return get_queried_object_id() ?: 0;
}

/**
 * Updates the rating on this item, and returns updated values.
 *
 * Passing int 1 to $rating will increment the 'yes' rating; passing int 0 will
 * increment the 'no' rating.
 *
 * If $id and/or $type are not specified, this function will attempt to guess
 * them.
 *
 * @param int $rating The rating type to increment.
 * @param int|null $id (Optional) The ID of the item to update.
 * @param string|null $type (Optional) The type of the item to update.
 *
 * @return array|null
 */
function update_rating( int $rating, ?int $id = null, ?string $type = null ) : ?array {
	$id = $id ?? get_item_id();
	$type = $type ?? get_item_type();
	if ( ! in_array( $type, ALLOWED_ITEM_TYPES ) ) {
		// Not a type of thing we can store a rating for.
		return null;
	}

	switch ( $rating ) {
		case 0:
			$key = META_KEY . '_no';
			break;
		case 1:
			$key = META_KEY . '_yes';
			break;
		default:
			$key = null;
			break;
	}

	if ( $key === null ) {
		// Invalid rating type.
		return null;
	}

	$current_value = get_metadata( $type, $id, $key, true ) ?? 0;
	$current_value ++;
	// Store incremented data.
	$updated = update_metadata( $type, $id, $key, $current_value );

	if ( false === $updated || is_wp_error( $updated ) ) {
		error_log( sprintf( 'Unable to %s update rating for %d.', $rating === 0 ? 'no' : 'yes', $id ) );
	}

	return get_current_rating( $id, $type );
}

/**
 * Get the current rating array for the current item.
 *
 * If $id and/or $type are not specified, this function will attempt to guess
 * them.
 *
 * @param int|null $id (Optional) The ID of the item to get a rating for.
 * @param string|null $type (Optional) The type of the item to get a rating for.
 *
 * @return int[]
 */
function get_current_rating( ?int $id = null, ?string $type = null ) : array {
	$id = $id ?? get_item_id();
	$type = $type ?? get_item_type();
	if ( ! in_array( $type, ALLOWED_ITEM_TYPES ) ) {
		return SCHEMA;
	}
	if ( $id === 0 ) {
		return SCHEMA;
	}
	$yes = (int) get_metadata( $type, $id, META_KEY . '_yes', true );
	$no = (int) get_metadata( $type, $id, META_KEY . '_no', true );

	return [
		'yes' => $yes,
		'no' => $no,
	];
}

/**
 * Test if the current $_POST might contain a rating submission.
 *
 * @return bool
 */
function maybe_rating_submission() : bool {
	// Without these values, it can't be a rating submission.
	return get_item_id( false ) && get_item_type( false );
}

/**
 * Check whether this was a valid submission.
 *
 * @return bool
 */
function verify_submission() : bool {
	$nonce = false !== wp_verify_nonce( filter_input( INPUT_POST, NONCE_NAME ), ACTION_NAME );
	$contains_rating = is_int( get_rating_submission() );

	return $nonce && $contains_rating;
}

/**
 * Get the rating from the current input.
 *
 * Should only be one of the following:
 * - 1 (thumbs up)
 * - 0 (thumbs down)
 * - null (no input)
 *
 * @return int|null
 */
function get_rating_submission() : ?int {
	return filter_input( INPUT_POST, 'rating', FILTER_VALIDATE_INT, [
		'options' => [
			'min_range' => 0,
			'max_range' => 1,
		],
		'flags' => FILTER_NULL_ON_FAILURE,
	] );
}

/**
 * Whether the current visitor has already rated this item.
 *
 * @return bool
 */
function has_visitor_already_rated() : bool {
	$cookie_value = filter_input( INPUT_COOKIE, get_cookie_name(), FILTER_VALIDATE_INT, [
		'options' => [
			'min_range' => 0,
			'max_range' => 1,
		],
	] );

	return apply_filters( 'hm_simple_rating_already_submitted', is_int( $cookie_value ) );
}
