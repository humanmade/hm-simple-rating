<?php

namespace HM\Simple_Rating;

use WP_Widget;

class Widget_Rate_Item extends WP_Widget {

	/**
	 * Create the widget object.
	 */
	public function __construct() {
		parent::__construct( 'rate_item', 'Rate Item', [
			'classname' => 'rate-item',
			'description' => "Allow visitors to rate whether this content was useful or not.",
		] );
	}

	/**
	 * Render the form for updating widget options in admin.
	 *
	 * @param $instance
	 *
	 * @return void
	 */
	public function form( $instance ) {
		$title = $instance['title'] ?? __( 'Did you find this page useful?', 'hm-simple-rating' );
		$show_count = isset( $instance['show_count'] ) && $instance['show_count'] === 'on';
		?>
		<p>
			<label for="<?php echo $this->get_field_name( 'title' ); ?>"><?php _e( 'Heading',
					'hm-simple-rating' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
					name="<?php echo $this->get_field_name( 'title' ); ?>" type="text"
					value="<?php echo esc_attr( $title ); ?>"/>
		</p>
		<p>
			<input type="checkbox" id="<?php echo $this->get_field_id( 'show_count' ); ?>"
					name="<?php echo $this->get_field_name( 'show_count' ); ?>"
				<?php echo $show_count ? esc_attr( 'checked' ) : null ?>/>
			<label for="<?php echo $this->get_field_name( 'show_count' ) ?>"><?php _e( 'Show rating count',
					'hm-simple-rating' ) ?></label>
			<br/>
			<i><?php printf( esc_html__( 'Users with the `%s` capability will always be able to see the count.',
					'hm-simple-rating' ), get_show_count_capability() ) ?></i>
		</p>
		<?php
	}

	/**
	 * Sanitize values before we save them.
	 *
	 * @param array $new_instance Incoming data.
	 * @param array $old_instance Old data.
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		// Remove any empty values.
		$new_instance = array_filter( $new_instance );

		$instance = [];

		if ( isset( $new_instance['title'] ) ) {
			$instance['title'] = strip_tags( $new_instance['title'] );
		}

		if ( isset( $new_instance['show_count'] ) ) {
			// Don't allow people to pass in odd values for this.
			$instance['show_count'] = 'on';
		}

		return $instance;
	}

	/**
	 * Widget render entry point.
	 *
	 * @param array $args Arguments for the widget (i.e. "before_widget).
	 * @param array $instance Instance-specific values (i.e. title).
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$id = $instance['item_id'] ?? get_item_id();
		$type = $instance['type'] ?? get_item_type();
		$title = $instance['title'] ?? null;
		$yes_text = $instance['yes_text'] ?? __( 'Yes', 'hm-simple-rating' );
		$no_text = $instance['no_text'] ?? __( 'No', 'hm-simple-rating' );
		$show_count = isset( $instance['show_count'] ) && $instance['show_count'] === 'on';
		$this->view( array_merge( $args, [
			'item_id' => $id,
			'type' => $type,
			'title' => $title,
			'yes_text' => $yes_text,
			'no_text' => $no_text,
			'show_count' => $show_count,
		] ) );
	}

	/**
	 * Render the widget.
	 *
	 * @param array $args Any arguments relevant to widget display.
	 *
	 * @return void
	 */
	protected function view( array $args ) : void {
		[
			'type' => $type,
			'item_id' => $id,
			'before_widget' => $before,
			'after_widget' => $after,
			'title' => $title,
			'before_title' => $before_title,
			'after_title' => $after_title,
			'yes_text' => $yes_text,
			'no_text' => $no_text,
			'show_count' => $show_count,
		] = $args;

		echo $before;

		if ( $title ) {
			printf(
				'%s%s%s',
				$before_title ?: '<h3 class="hm-simple-rating__title">',
				$title,
				$after_title ?: '</h3>'
			);
		}
		$current_rating = get_current_rating( $id, $type );
		$yes_count = $current_rating['yes'] ?: 0;
		$no_count = $current_rating['no'] ?: 0;
		?>
		<form method="POST" class="hm-simple-rating__widget">
			<?php wp_nonce_field( ACTION_NAME, NONCE_NAME, true, true ) ?>
			<input type="hidden" name="item_type" value="<?php echo esc_attr( $type ) ?>">
			<input type="hidden" name="item_to_rate" value="<?php echo esc_attr( $id ) ?>">
			<?php
			$this->render_button( 'yes', $yes_text, $yes_count,
				apply_filters( 'hm_simple_rating_disable_button', has_visitor_already_rated(), $args, 'yes' ),
				apply_filters( 'hm_simple_rating_show_count', $show_count, $args, 'yes' ) );
			$this->render_button( 'no', $no_text, $no_count,
				apply_filters( 'hm_simple_rating_disable_button', has_visitor_already_rated(), $args, 'no' ),
				apply_filters( 'hm_simple_rating_show_count', $show_count, $args, 'no' ) );
			?>
		</form>
		<?php
		echo $after;
	}

	/**
	 * Render a "yes" or "no" button.
	 *
	 * @param string $type Either "yes" or "no".
	 * @param string $text String for the option, i.e. "Yes" or "No".
	 * @param int $count The number of votes for this type.
	 * @param bool $disable Whether to disable the button (i.e. if a user has already voted).
	 * @param bool $show_count Whether to show the count or hide it.
	 *
	 * @return void
	 */
	protected function render_button(
		string $type,
		string $text,
		int $count,
		bool $disable = false,
		bool $show_count = true
	) : void {
		$value = $type === 'yes' ? 1 : 0;
		?>
		<button name="rating"
				value="<?php echo esc_attr( $value ) ?>" <?php echo esc_attr( $disable ? 'disabled' : '' ) ?>
				class="hm-simple-rating__rate-button hm-simple-rating__rate-button--<?php echo esc_attr( $type ) ?>">
				<span class="hm-simple-rating__icon">
					<?php do_action( "hm_simple_rating_icon_$type", $type, $text, $count, $disable, $show_count ) ?>
				</span>
			<span class="hm-simple-rating__text">
					<?php echo esc_html( $text ) ?>
				</span>
			<?php if ( $show_count ) { ?>
				<span class="hm-simple-rating__count">
					<?php echo esc_html( $count ) ?>
				</span>
			<?php } ?>
		</button>
		<?php
	}

	/**
	 * Renders "yes" icon when hooked to action.
	 *
	 * @return void
	 */
	public static function render_yes_icon() : void {
		?>
		👍
		<?php
	}

	/**
	 * Renders "no" icon when hooked to action.
	 *
	 * @return void
	 */
	public static function render_no_icon() : void {
		?>
		👎
		<?php
	}
}
