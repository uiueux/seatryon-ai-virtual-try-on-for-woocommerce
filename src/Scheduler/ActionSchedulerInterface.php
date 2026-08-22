<?php
/**
 * Action Scheduler contract.
 *
 * @package SeaTryOn
 */

namespace SeaTryOn\Scheduler;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing,Squiz.Commenting.FunctionComment.MissingParamTag,Squiz.Commenting.FunctionComment.ParamNameNoMatch

/** Narrow public Action Scheduler API boundary. */
interface ActionSchedulerInterface {
	public function is_available(): bool;
	/** @param array<string,int|string> $args */
	public function schedule_single( int $timestamp, string $hook, array $args, string $group, bool $unique ): int;
	/**
	 * Schedule one recurring action through the public API.
	 *
	 * @param array<string,int|string> $args Privacy-safe action arguments.
	 */
	public function schedule_recurring( int $timestamp, int $interval, string $hook, array $args, string $group, bool $unique ): int;
	/** @param array<string,int|string> $args */
	public function has_scheduled( string $hook, array $args, string $group ): bool;
	/** @param array<string,int|string> $args */
	public function unschedule_all( string $hook, array $args, string $group ): int;
}
