<?php
/**
 * Widget view.
 *
 * @var array $data Template data.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! is_array( $data ) || ! array_key_exists( 'scanData', $data ) || ! array_key_exists( 'pluginUrl', $data ) || ! array_key_exists( 'features', $data ) ) {
	return;
}

/**
 * Template data.
 *
 * @var \CloudLinux\Imunify\App\Model\ScanData $scanData
 * @var \CloudLinux\Imunify\App\Model\Feature[] $features
 * @var \CloudLinux\Imunify\App\Model\MalwareItem[] $malwareItems
 * @var int $totalItemsCount
 * @var bool $showMoreButton
 * @var string $showMoreUrl
 * @var bool $showUpgradeButton
 * @var string $upgradeUrl
 * @var string $statusTitle
 * @var string $statusIcon
 */
$scanData          = $data['scanData'];
$pluginUrl         = $data['pluginUrl'];
$features          = $data['features'];
$malwareItems      = $data['malwareItems'];
$totalItemsCount   = $data['totalItemsCount'];
$showMoreButton    = $data['showMoreButton'];
$showMoreUrl       = $data['showMoreUrl'];
$showUpgradeButton = isset( $data['showUpgradeButton'] ) && $data['showUpgradeButton'];
$upgradeUrl        = $data['upgradeUrl'];
$statusTitle       = $data['statusTitle'];
$statusIcon        = $data['statusIcon'];
$malwareUrl        = isset( $data['malwareUrl'] ) ? $data['malwareUrl'] : '';
$wafUrl            = isset( $data['wafUrl'] ) ? $data['wafUrl'] : '';

$lastScanTime = $scanData->getLastScanTimestamp();
$nextScanTime = $scanData->getNextScanTimestamp();

// Rule incident display data.
$showWafSection = isset( $data['showWafSection'] ) && $data['showWafSection'];
$wafEnabled     = isset( $data['wafEnabled'] ) && $data['wafEnabled'];
$wafMonitoring  = isset( $data['wafMonitoring'] ) && $data['wafMonitoring'];
$rules          = isset( $data['rules'] ) ? $data['rules'] : array();
$ruleset        = isset( $data['ruleset'] ) ? $data['ruleset'] : '';

// Phase-1 bot protection section markup (DEF-42031). BotProtectionWidgetSection
// builds the HTML with its own escaping, so we echo it without re-escaping.
$botProtectionRowHtml  = isset( $data['botProtectionRowHtml'] ) ? $data['botProtectionRowHtml'] : '';
$botProtectionPaneHtml = isset( $data['botProtectionPaneHtml'] ) ? $data['botProtectionPaneHtml'] : '';

// Calculate total incidents count for navigation link.
$totalIncidentsCount = 0;
if ( ! empty( $rules ) ) {
	foreach ( $rules as $rule ) {
		$totalIncidentsCount += $rule->incidentsCount;
	}
}

use CloudLinux\Imunify\App\Helpers\DateTimeFormatter;
use CloudLinux\Imunify\App\Helpers\Documentation;
use CloudLinux\Imunify\App\Helpers\PathFormatter;
?>
<div class="imunify-security__widget">
	<!-- Main Pane -->
	<div class="imunify-security__pane js-pane js-pane-main">
		<div class="imunify-security__overview">
			<div class="imunify-security__overview-main">
				<div class="imunify-security__status">
					<div class="imunify-security__status-icon">
						<img src="<?php echo esc_url( $pluginUrl . 'assets/images/' . $statusIcon ); ?>" alt="Protected status" width="80" height="80">
					</div>
					<div class="imunify-security__status-title"><?php echo esc_html( $statusTitle ); ?></div>
				</div>
				<?php if ( $showUpgradeButton ) : ?>
				<div class="imunify-security__get-protected">
					<a href="<?php echo esc_url( $upgradeUrl ); ?>" class="button button-primary">
						<?php esc_html_e( 'Get protected', 'imunify-security' ); ?>
					</a>
				</div>
				<?php endif; ?>
			</div>

			<div class="imunify-security__overview-details">
				<div class="imunify-security__overview-rows">
					<?php foreach ( $features as $feature ) : ?>
					<div class="imunify-security__overview-row imunify-security__overview-row--feature">
						<span class="imunify-security__overview-label">
							<a href="<?php echo esc_url( $feature->getUrl() ); ?>" target="_blank">
								<?php echo esc_html( $feature->getName() ); ?>
							</a>
						</span>
						<span class="imunify-security__overview-value <?php echo esc_attr( 'imunify-security__overview-value--' . strtolower( $feature->getStatus() ) ); ?>">
							<?php echo esc_html( $feature->getStatusLabel() ); ?>
						</span>
					</div>
					<?php endforeach; ?>
					<div class="imunify-security__overview-row imunify-security__overview-row--scan imunify-security__overview-row--separator">
						<span class="imunify-security__overview-label"><?php esc_html_e( 'Last scan:', 'imunify-security' ); ?></span>
						<span class="imunify-security__overview-value">
						<?php
						if ( $lastScanTime > 0 ) {
							echo esc_html( DateTimeFormatter::formatScanTime( $lastScanTime ) );
						} else {
							esc_html_e( 'never', 'imunify-security' );
						}
						?>
						</span>
					</div>
					<div class="imunify-security__overview-row imunify-security__overview-row--scan">
						<span class="imunify-security__overview-label"><?php esc_html_e( 'Next scan:', 'imunify-security' ); ?></span>
						<span class="imunify-security__overview-value">
						<?php
						if ( $nextScanTime > 0 ) {
							echo esc_html( DateTimeFormatter::formatScanTime( $nextScanTime ) );
						} else {
							esc_html_e( 'not scheduled', 'imunify-security' );
						}
						?>
						</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Navigation Links -->
		<div class="imunify-security__nav-links">
			<?php if ( empty( $malwareItems ) ) : ?>
			<div class="imunify-security__nav-link imunify-security__nav-link--static imunify-security__nav-link--malware">
				<span class="imunify-security__nav-link-text"><?php esc_html_e( 'Malware Protection', 'imunify-security' ); ?></span>
				<span class="imunify-security__nav-link-status imunify-security__nav-link-status--monitoring"><?php esc_html_e( 'No malware found', 'imunify-security' ); ?></span>
			</div>
			<?php else : ?>
			<a href="#" class="imunify-security__nav-link js-nav-link" data-pane="malware">
				<span class="imunify-security__nav-link-text">
					<?php
					/* translators: %d: number of malware items */
					echo esc_html( sprintf( __( 'Malware Protection (%d)', 'imunify-security' ), $totalItemsCount ) );
					?>
				</span>
				<span class="imunify-security__nav-link-arrow dashicons dashicons-arrow-right-alt2"></span>
			</a>
			<?php endif; ?>

			<?php if ( $wafEnabled || $wafMonitoring ) : ?>
				<?php if ( $showWafSection && ! empty( $rules ) ) : ?>
			<a href="#" class="imunify-security__nav-link js-nav-link" data-pane="incidents">
				<span class="imunify-security__nav-link-text">
					<?php
					/* translators: %d: number of WAF incidents */
					echo esc_html( sprintf( __( 'Web Application Firewall (%d)', 'imunify-security' ), $totalIncidentsCount ) );
					?>
				</span>
				<span class="imunify-security__nav-link-arrow dashicons dashicons-arrow-right-alt2"></span>
			</a>
				<?php elseif ( $wafEnabled ) : ?>
			<div class="imunify-security__nav-link imunify-security__nav-link--static">
				<span class="imunify-security__nav-link-text">
					<?php esc_html_e( 'Web Application Firewall', 'imunify-security' ); ?>
				</span>
				<span class="imunify-security__nav-link-status imunify-security__nav-link-status--enabled"><?php esc_html_e( 'Enabled', 'imunify-security' ); ?></span>
			</div>
				<?php else : ?>
			<div class="imunify-security__nav-link imunify-security__nav-link--static">
				<span class="imunify-security__nav-link-text">
					<?php esc_html_e( 'Web Application Firewall', 'imunify-security' ); ?>
				</span>
				<span class="imunify-security__nav-link-status imunify-security__nav-link-status--monitoring"><?php esc_html_e( 'Monitoring', 'imunify-security' ); ?></span>
			</div>
				<?php endif; ?>
			<?php endif; ?>

			<?php
			// Bot Protection row (DEF-42031). Rendered by BotProtectionWidgetSection so
			// the escaping + nonce handling live next to the state logic.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- section renders pre-escaped markup.
			echo $botProtectionRowHtml;
			?>
		</div>

		<!-- Widget Actions -->
		<div class="imunify-security__widget-actions">
			<?php
			// Documentation::link() returns a pre-escaped anchor.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in Documentation::link().
			echo Documentation::link( Documentation::home() );
			?>
			<a href="#" class="imunify-security__action-link js-hide-notifications"><?php esc_html_e( 'Hide notifications', 'imunify-security' ); ?></a>
		</div>
	</div>

	<!-- Malware Pane -->
	<?php if ( ! empty( $malwareItems ) ) : ?>
	<div class="imunify-security__pane js-pane js-pane-malware" style="display: none;">
		<div class="imunify-security__pane-header">
			<a href="#" class="imunify-security__back-link js-back-link">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
			</a>
			<span class="imunify-security__pane-title">
				<?php
				/* translators: %d: number of malware items found */
				echo esc_html( sprintf( _n( '%d malware found', '%d malware found', $totalItemsCount, 'imunify-security' ), $totalItemsCount ) );
				?>
			</span>
		</div>
		<div class="imunify-security__malware">
			<div class="imunify-security__malware-list">
				<?php foreach ( $malwareItems as $malware ) : ?>
				<div class="imunify-security__malware-row">
					<div class="imunify-security__malware-header">
						<div class="imunify-security__malware-path"><?php echo esc_html( PathFormatter::formatLongPath( $malware->getPath() ) ); ?></div>
						<span class="imunify-security__malware-status <?php echo esc_attr( $malware->getStatusExtraCssClass( 'imunify-security__malware-status' ) ); ?>"><?php echo esc_html( $malware->getStatusLabel() ); ?></span>
					</div>
					<div class="imunify-security__malware-details">
						<span class="imunify-security__malware-signature"><?php echo esc_html( $malware->getSignature() ); ?></span>
						<span class="imunify-security__malware-detected"><?php echo esc_html( DateTimeFormatter::formatDetectionDate( $malware->getLastActionDate() ) ); ?></span>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<div class="imunify-security__pane-footer">
				<a href="<?php echo esc_url( $malwareUrl ); ?>" class="imunify-security__more-link"><?php esc_html_e( 'Show more results', 'imunify-security' ); ?></a>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- Incidents Pane -->
	<?php if ( $showWafSection && ! empty( $rules ) ) : ?>
	<div class="imunify-security__pane js-pane js-pane-incidents" style="display: none;">
		<div class="imunify-security__pane-header">
			<a href="#" class="imunify-security__back-link js-back-link">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
			</a>
			<span class="imunify-security__pane-title">
				<?php esc_html_e( 'Latest incidents (7 days)', 'imunify-security' ); ?>
				<?php if ( $wafMonitoring ) : ?>
				<span class="imunify-security__badge imunify-security__badge--monitoring js-waf-monitoring-tooltip"><?php esc_html_e( 'Monitoring', 'imunify-security' ); ?></span>
				<?php endif; ?>
			</span>
			<?php if ( $ruleset ) : ?>
			<span class="imunify-security__pane-version"><?php echo esc_html( sprintf( 'v%s', $ruleset ) ); ?></span>
			<?php endif; ?>
		</div>
		<div class="imunify-security__incidents">
			<table class="imunify-security__incidents-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'imunify-security' ); ?></th>
						<th><?php esc_html_e( 'Component', 'imunify-security' ); ?></th>
						<th><?php esc_html_e( 'CVE', 'imunify-security' ); ?></th>
						<th><?php esc_html_e( 'Severity', 'imunify-security' ); ?></th>
						<th><?php esc_html_e( 'Count', 'imunify-security' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rules as $rule ) : ?>
						<?php if ( $rule->incidentsCount > 0 ) : ?>
					<tr>
						<td class="imunify-security__incidents-date"><?php echo esc_html( $rule->lastIncidentDateFormatted ); ?></td>
						<td class="imunify-security__incidents-component"><?php echo esc_html( $rule->component ); ?></td>
						<td class="imunify-security__incidents-cve">
							<?php if ( ! empty( $rule->cveLink ) ) : ?>
							<a href="<?php echo esc_url( $rule->cveLink ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $rule->cve ); ?></a>
							<?php elseif ( ! empty( $rule->cve ) ) : ?>
								<?php echo esc_html( $rule->cve ); ?>
							<?php else : ?>
							<span class="imunify-security__incidents-na">-</span>
							<?php endif; ?>
						</td>
						<td class="imunify-security__incidents-severity">
							<?php
							$severityClass = '';
							if ( $rule->severity >= 7 ) {
								$severityClass = 'imunify-security__severity--high';
							} elseif ( $rule->severity >= 4 ) {
								$severityClass = 'imunify-security__severity--medium';
							} else {
								$severityClass = 'imunify-security__severity--low';
							}
							?>
							<span class="imunify-security__severity <?php echo esc_attr( $severityClass ); ?>"><?php echo esc_html( number_format( $rule->severity, 1 ) ); ?></span>
						</td>
						<td class="imunify-security__incidents-count"><?php echo esc_html( $rule->incidentsCount ); ?></td>
					</tr>
					<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="imunify-security__pane-footer imunify-security__pane-footer--split">
			<?php
			// Documentation::link() returns a pre-escaped anchor.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in Documentation::link().
			echo Documentation::link( Documentation::waf() );
			?>
			<a href="<?php echo esc_url( $wafUrl ); ?>" class="imunify-security__more-link"><?php esc_html_e( 'Show more results', 'imunify-security' ); ?></a>
		</div>
	</div>
	<?php endif; ?>

	<?php
	// Bot Protection pane (DEF-42031). Rendered by BotProtectionWidgetSection.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- section renders pre-escaped markup.
	echo $botProtectionPaneHtml;
	?>
</div>

<div class="imunify-security__snooze-panel" style="display: none;">
	<form class="imunify-security__snooze-form">
		<label for="imunify-snooze-weeks"><?php esc_html_e( 'Snooze for:', 'imunify-security' ); ?></label>
		<select id="imunify-snooze-weeks" name="weeks">
			<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
				<option value="<?php echo esc_attr( $i ); ?>">
					<?php
					/* translators: %d: number of weeks */
					echo esc_html( sprintf( _n( '%d week', '%d weeks', $i, 'imunify-security' ), $i ) );
					?>
				</option>
			<?php endfor; ?>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Snooze', 'imunify-security' ); ?></button>
		<a href="#" class="imunify-security__snooze-close js-snooze-close" title="<?php esc_attr_e( 'Close', 'imunify-security' ); ?>">
			<span class="dashicons dashicons-no-alt"></span>
		</a>
	</form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	var animDuration = 150;

	// Navigate to a pane
	$('.imunify-security__widget').on('click', '.js-nav-link', function(e) {
		e.preventDefault();
		var $widget = $(this).closest('.imunify-security__widget');
		var target = $(this).data('pane');
		var $mainPane = $widget.find('.js-pane-main');
		var $targetPane = $widget.find('.js-pane-' + target);

		// Fade out main pane, then fade in target pane
		$mainPane.fadeOut(animDuration, function() {
			$targetPane.fadeIn(animDuration);
		});
	});

	// Navigate back to main pane
	$('.imunify-security__widget').on('click', '.js-back-link', function(e) {
		e.preventDefault();
		var $widget = $(this).closest('.imunify-security__widget');
		var $currentPane = $(this).closest('.js-pane');
		var $mainPane = $widget.find('.js-pane-main');

		// Fade out current pane, then fade in main pane
		$currentPane.fadeOut(animDuration, function() {
			$mainPane.fadeIn(animDuration);
		});
	});
});
</script>
