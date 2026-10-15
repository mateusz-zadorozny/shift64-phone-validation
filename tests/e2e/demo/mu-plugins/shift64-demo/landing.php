<?php
/**
 * Front page of a demo site (template_include from shift64-demo.php): the
 * scenario landing page on a scenario subsite, the scenario index on the
 * network root. Demo-only, plain HTML: no theme, no JS, English only.
 *
 * @package Shift64\SmartPhoneValidation
 */

$shift64_demo_slug      = shift64_demo_current_slug();
$shift64_demo_scenarios = shift64_demo_scenarios();
$shift64_demo_is_index  = 'index' === $shift64_demo_slug;
$shift64_demo_scenario  = null;
foreach ( $shift64_demo_scenarios as $shift64_demo_candidate ) {
	if ( $shift64_demo_candidate['slug'] === $shift64_demo_slug ) {
		$shift64_demo_scenario = $shift64_demo_candidate;
	}
}
$shift64_demo_network = network_home_url( '/' );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by the buy endpoint.
$shift64_demo_seeded = null !== shift64_demo_product_id() && ( $_GET['shift64_demo'] ?? '' ) !== 'missing';

/**
 * What the site is actually configured to do, read back from its options.
 *
 * @return string[]
 */
$shift64_demo_chips = static function (): array {
	$option   = static fn( string $key, string $default ): string => (string) get_option( 'shift64_phone_validation_' . $key, $default );
	$checkout = (int) get_option( 'woocommerce_checkout_page_id' );
	$classic  = get_page_by_path( 'classic-checkout' );
	$chips    = array(
		'Locale: ' . get_locale(),
		'Checkout: ' . ( $classic && $classic->ID === $checkout ? 'classic' : 'block' ),
	);
	if ( 'yes' !== $option( 'enabled', 'yes' ) ) {
		$chips[] = 'Validation: off';
		return $chips;
	}
	$chips[] = 'Mode: ' . ( 'international_only' === $option( 'validation_mode', 'default_and_international' ) ? 'international only' : 'default country + international' );
	$chips[] = 'yes' === $option( 'format_on_save', 'yes' )
		? 'Format: ' . str_replace( array( 'E164', 'INTERNATIONAL', 'NATIONAL' ), array( 'E.164', 'international', 'national' ), $option( 'output_format', 'E164' ) )
		: 'Format: as typed';
	return $chips;
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $shift64_demo_is_index ? 'Phone validation scenarios' : $shift64_demo_scenario['title'] ); ?> · SHIFT64 demo</title>
<style>
*{box-sizing:border-box}
body{margin:0;font:16px/1.5 -apple-system,"Segoe UI",Roboto,sans-serif;color:#1e1e1e;background:#f6f7f7}
.bar{background:#1e1e1e;color:#fff;padding:8px 24px;font-size:13px}
.bar a{color:#9ec2e6}
main{max-width:880px;margin:0 auto;padding:40px 24px}
h1{font-size:34px;margin:0 0 6px}
.lead{color:#50575e;margin:0 0 18px;font-size:18px}
.chips span{display:inline-block;background:#fff;border:1px solid #dcdcde;border-radius:999px;padding:3px 12px;font-size:13px;margin:0 6px 6px 0}
.cta{display:block;text-align:center;background:#007cba;color:#fff;text-decoration:none;font-size:24px;font-weight:600;padding:26px;border-radius:8px;margin:26px 0;outline:3px solid transparent;outline-offset:2px}
.cta:hover{background:#006ba1}
.cta:focus-visible{outline-color:#f0b849}
.missing{background:#fcf0f1;border:1px solid #d63638;border-radius:8px;padding:16px 20px;margin:26px 0}
.try{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px}
.try h2,.other h2{font-size:15px;text-transform:uppercase;letter-spacing:.06em;color:#50575e;margin:0 0 10px}
.try table{width:100%;border-collapse:collapse;font-size:15px}
.try td{padding:6px 4px;border-top:1px solid #f0f0f1}
code{background:#f0f0f1;padding:1px 6px;border-radius:4px}
.ok{color:#008a20;font-weight:600}
.bad{color:#d63638;font-weight:600}
.admin{font-size:14px;margin:14px 0 0;color:#50575e}
.admin a{color:#007cba}
.other{margin-top:36px}
.grid{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px}
.grid a{display:block;height:100%;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;text-decoration:none;color:#1e1e1e}
.grid a:focus-visible{outline:3px solid #f0b849;outline-offset:2px}
.grid a strong{display:block;color:#007cba}
.grid a small{display:block;color:#50575e}
.grid a[aria-current]{border:2px solid #007cba;background:#f0f6fc}
.note{font-size:12px;color:#787c82;margin-top:28px}
</style>
</head>
<body>
<div class="bar">SHIFT64 Phone Validation · demo network · <a href="<?php echo esc_url( $shift64_demo_network ); ?>"><?php echo esc_html( preg_replace( '#^https?://#', '', home_url( '/' ) ) ); ?></a></div>
<main>
<?php if ( $shift64_demo_is_index ) : ?>
	<h1>Phone validation scenarios</h1>
	<p class="lead">One store per configuration. Pick a scenario, press the big button, and you land on its checkout with one product in the cart.</p>
	<nav aria-label="Scenarios">
		<ul class="grid">
		<?php foreach ( $shift64_demo_scenarios as $shift64_demo_item ) : ?>
			<li><a href="<?php echo esc_url( $shift64_demo_network . $shift64_demo_item['slug'] . '/' ); ?>"><strong><?php echo esc_html( $shift64_demo_item['title'] ); ?></strong><small>/<?php echo esc_html( $shift64_demo_item['slug'] ); ?>/</small><small><?php echo esc_html( $shift64_demo_item['description'] ); ?></small></a></li>
		<?php endforeach; ?>
		</ul>
	</nav>
	<p class="admin"><a href="<?php echo esc_url( network_admin_url() ); ?>">Network Admin</a> (log in as <code>admin</code> first).</p>
<?php else : ?>
	<h1><?php echo esc_html( $shift64_demo_scenario['title'] ); ?></h1>
	<p class="lead"><?php echo esc_html( $shift64_demo_scenario['description'] ); ?></p>
	<div class="chips">
	<?php foreach ( $shift64_demo_chips() as $shift64_demo_chip ) : ?>
		<span><?php echo esc_html( $shift64_demo_chip ); ?></span>
	<?php endforeach; ?>
	</div>

	<?php if ( $shift64_demo_seeded ) : ?>
		<a class="cta" href="<?php echo esc_url( add_query_arg( 'shift64_demo', 'buy', home_url( '/' ) ) ); ?>">Add to cart &amp; go to checkout →</a>
	<?php else : ?>
		<p class="missing" role="alert"><strong>Store not seeded.</strong> WooCommerce or the demo product is missing on this site. Run <code>npm run demo:start</code> to seed it again.</p>
	<?php endif; ?>

	<section class="try" aria-labelledby="shift64-demo-try">
		<h2 id="shift64-demo-try">Try</h2>
		<table>
		<?php foreach ( $shift64_demo_scenario['try'] as $shift64_demo_try ) : ?>
			<tr>
				<td><code><?php echo esc_html( $shift64_demo_try['phone'] ); ?></code></td>
				<td class="<?php echo $shift64_demo_try['valid'] ? 'ok' : 'bad'; ?>"><?php echo $shift64_demo_try['valid'] ? 'accepted' : 'rejected'; ?></td>
				<td><?php echo esc_html( $shift64_demo_try['result'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</table>
	</section>

	<p class="admin"><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=phone_validation' ) ); ?>">This site's phone settings</a> · <a href="<?php echo esc_url( network_admin_url() ); ?>">Network Admin</a> (log in as <code>admin</code> first).</p>

	<nav class="other" aria-label="Scenarios">
		<h2>Other scenarios</h2>
		<ul class="grid">
			<li><a href="<?php echo esc_url( $shift64_demo_network ); ?>"><strong>All scenarios</strong><small>/ (network index)</small></a></li>
		<?php foreach ( $shift64_demo_scenarios as $shift64_demo_item ) : ?>
			<li><a href="<?php echo esc_url( $shift64_demo_network . $shift64_demo_item['slug'] . '/' ); ?>"<?php echo $shift64_demo_item['slug'] === $shift64_demo_slug ? ' aria-current="page"' : ''; ?>><strong><?php echo esc_html( $shift64_demo_item['title'] ); ?></strong><small>/<?php echo esc_html( $shift64_demo_item['slug'] ); ?>/</small></a></li>
		<?php endforeach; ?>
		</ul>
	</nav>

	<p class="note">Settings are reset to this scenario on every <code>npm run demo:start</code>.</p>
<?php endif; ?>
</main>
</body>
</html>
