<?php
/**
 * What the licensing SDK says about this installation.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads edition, upgrade and beta state from the SDK in one place.
 */
final class Licensing {

	/**
	 * May premium code run on this site?
	 *
	 * @return bool
	 */
	public static function can_use_premium(): bool {
		return function_exists( 'pbsw_fs' ) && pbsw_fs()->can_use_premium_code();
	}

	/**
	 * Is this site on the Agency plan (white label, client review, multisite network features)?
	 *
	 * Agency code lives only in premium paths, so the free package never reaches this with `true`.
	 *
	 * @return bool
	 */
	public static function is_agency(): bool {
		// ⛔ Ask the admin kit, never the SDK's is_plan(): that ranks plans by store position, and
		// the legacy plan (673) predates Agency, so an unlimited legacy licence would never be
		// Agency here although D34 makes it one (docs/843; LegacyAgencyGateTest).
		$agency = 'agency' === \ZinnDigital\PBS\AdminKit\Licence::tier();

		/**
		 * Filters whether the Agency plan's features are available on this site.
		 *
		 * @param bool $agency True on the Agency plan.
		 */
		return (bool) apply_filters( 'pbsw_is_agency', $agency );
	}

	/**
	 * The upgrade URL, or null when there is nothing to sell (premium already usable).
	 *
	 * ⭐ Upsells are runtime-gated rather than build-gated (CONTRACT §3): there are no free-only
	 * files, so the same code decides at run time whether an upgrade prompt makes sense.
	 *
	 * @return string|null
	 */
	public static function upgrade_url(): ?string {
		if ( ! function_exists( 'pbsw_fs' ) || self::can_use_premium() ) {
			return null;
		}
		$url = (string) pbsw_fs()->get_upgrade_url();

		return '' === $url ? null : $url;
	}

	/**
	 * Beta-update state (sh-r4).
	 *
	 * The SDK runs the beta programme: a licensed, connected installation opts in from its
	 * Account page, and from then on is offered beta versions as updates. Only the premium
	 * package can join (the SDK registers its opt-in handler for premium code only), so the
	 * free edition reports `available: false`.
	 *
	 * @return array{available: bool, enabled: bool, accountUrl: string}
	 */
	public static function beta(): array {
		$state = array(
			'available'  => false,
			'enabled'    => false,
			'accountUrl' => '',
		);
		if ( ! function_exists( 'pbsw_fs' ) ) {
			return $state;
		}

		$fs = pbsw_fs();
		if ( $fs->is_registered() ) {
			$state['accountUrl'] = (string) $fs->get_account_url();
		}
		$state['available'] = $fs->is_premium() && $fs->is_registered();
		if ( $state['available'] ) {
			$site             = $fs->get_site();
			$state['enabled'] = is_object( $site ) && method_exists( $site, 'is_beta' ) && $site->is_beta();
		}

		return $state;
	}
}
