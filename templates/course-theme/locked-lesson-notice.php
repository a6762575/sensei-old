<?php
/**
 * The Template for displaying locked lesson notice in lesson page when using Course Theme.
 *
 * @author      Automattic
 * @package     Sensei
 * @category    Templates
 * @version     3.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'sensei_locked_lesson_notices_map' ) ) {
	/**
	 * Notices map to echo notices HTML.
	 *
	 * @param array $notice
	 */
	function sensei_locked_lesson_notices_map( $notice ) {
		?>
		<div class="sensei-lms-notice sensei-course-theme-locked-lesson-notice">
			<?php if ( ! empty( $notice['title'] ) ) { ?>
			<div class="sensei-course-theme-locked-lesson-notice__header">
				<?php if ( ! empty( $notice['icon'] ) ) { ?>
				<div class="sensei-course-theme-locked-lesson-notice__icon">
					<?php
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the function.
					echo Sensei()->assets->get_icon( $notice['icon'] );
					?>
				</div>
				<?php } ?>
				<h3 class="sensei-course-theme-locked-lesson-notice__title">
					<?php echo wp_kses_post( $notice['title'] ); ?>
				</h3>
			</div>
			<?php } ?>
			<p class="sensei-course-theme-locked-lesson-notice__text"><?php echo wp_kses_post( $notice['text'] ); ?></p>

			<?php if ( ! empty( $notice['actions'] ) ) { ?>
				<ul class="sensei-course-theme-locked-lesson-notice__actions">
				<?php implode( '', array_map( 'sensei_locked_lesson_notice_actions_map', $notice['actions'] ) ); ?>
				</ul>
			<?php } ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'sensei_locked_lesson_notice_actions_map' ) ) {
	/**
	 * Notice actions map to echo the actions.
	 *
	 * @param array|string $action
	 */
	function sensei_locked_lesson_notice_actions_map( $action ) {
		?>
		<li>
			<?php
			if ( ! is_array( $action ) ) {
				echo wp_kses_post( $action );
			} else {
				?>
				<a href="<?php echo esc_url( $action['url'] ); ?>" class="sensei-course-theme__button is-<?php echo esc_attr( $action['style'] ); ?>">
					<?php echo wp_kses_post( $action['label'] ); ?>
				</a>
				<?php
			}
			?>
		</li>
		<?php
	}
}

?>
<div>
	<?php
	// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- A template variable
	implode( '', array_map( 'sensei_locked_lesson_notices_map', $notices ) );
	?>
</div>
