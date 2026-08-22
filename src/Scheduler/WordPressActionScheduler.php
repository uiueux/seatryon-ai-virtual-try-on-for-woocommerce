<?php
/**
 * WordPress Action Scheduler adapter.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Scheduler;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing

/** Calls only Action Scheduler's documented public procedural API. */
final class WordPressActionScheduler implements ActionSchedulerInterface {
	public function is_available(): bool {
		return function_exists( 'as_schedule_single_action' ) && function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_unschedule_all_actions' );
	}
	/**
	 * Schedule recurring action through the public API.
	 *
	 * @param int                      $timestamp First run timestamp.
	 * @param int                      $interval  Interval in seconds.
	 * @param string                   $hook      Action hook.
	 * @param array<string,int|string> $args      Privacy-safe action arguments.
	 * @param string                   $group     Action group.
	 * @param bool                     $unique    Whether the action is unique.
	 */
	public function schedule_recurring( int $timestamp, int $interval, string $hook, array $args, string $group, bool $unique ): int {
		if ( ! $this->is_available() ) {
			return 0;
		}
		// @phpstan-ignore-next-line The function is guarded above and supplied by WooCommerce.
		return (int) call_user_func( 'as_schedule_recurring_action', $timestamp, $interval, $hook, $args, $group, $unique );
	}
	public function schedule_single( int $timestamp, string $hook, array $args, string $group, bool $unique ): int {
		if ( ! $this->is_available() ) {
			return 0; }
		// @phpstan-ignore-next-line The function is guarded above and supplied by WooCommerce.
		return (int) call_user_func( 'as_schedule_single_action', $timestamp, $hook, $args, $group, $unique );
	}
	public function has_scheduled( string $hook, array $args, string $group ): bool {
		// @phpstan-ignore-next-line The function is guarded by is_available().
		return $this->is_available() && false !== call_user_func( 'as_has_scheduled_action', $hook, $args, $group );
	}
	public function unschedule_all( string $hook, array $args, string $group ): int {
		if ( ! $this->is_available() ) {
			return 0;
		}
		// @phpstan-ignore-next-line The function is guarded above and supplied by WooCommerce.
		return (int) call_user_func( 'as_unschedule_all_actions', $hook, $args, $group );
	}
}
