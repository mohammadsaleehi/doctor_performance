/* admin.js — WP Performance Doctor admin interactivity */

( function () {
	'use strict';

	// --- Shared state ---
	var dashboard   = window.wpdDashboard   || {};
	var monitoring  = window.wpdMonitoring  || {};
	var diagnostics = window.wpdDiagnostics || {};
	var recommendationsPage = window.wpdRecommendationsPage || {};

	var dashboardRestUrl   = dashboard.restUrl   || '';
	var dashboardNonce     = dashboard.nonce   || '';
	var monitoringRestUrl  = monitoring.restUrl  || '';
	var monitoringNonce    = monitoring.nonce   || '';
	var wpRestNonce        = dashboard.wpRestNonce || monitoring.wpRestNonce || diagnostics.wpRestNonce || recommendationsPage.wpRestNonce || '';
	var dStrings           = dashboard.strings   || {};
	var mStrings           = monitoring.strings   || {};
	var diagStrings        = diagnostics.strings || {};
	var recStrings         = recommendationsPage.strings || {};
	var diagRestUrl        = diagnostics.restUrl || '';
	var findingsRestUrl    = '';
	var recRestUrl         = recommendationsPage.recommendationsRestUrl || '';
	var diagNonce          = diagnostics.nonce   || '';
	var recNonce           = recommendationsPage.nonce   || '';

	// Read inline JSON configs for additional URLs.
	var diagConfigEl = document.getElementById( 'wpd-diagnostics-config' );
	if ( diagConfigEl ) {
		try {
			var diagCfg = JSON.parse( diagConfigEl.textContent );
			findingsRestUrl = diagCfg.findingsRestUrl || '';
			wpRestNonce     = diagCfg.wpRestNonce || wpRestNonce;
			diagNonce       = diagCfg.nonce || diagNonce;
		} catch ( e ) {}
	}

	// --- Generic helpers ---

	/**
	 * Generic notice display.
	 */
	function showNotice( element, message, type ) {
		if ( ! element ) {
			return;
		}
		element.hidden = false;
		element.textContent = message;
		element.className = 'wpd-notice wpd-notice--' + type + ' wpd-notice--inline';
		if ( 'info' !== type ) {
			setTimeout( function () { element.hidden = true; }, 5000 );
		}
	}

	/**
	 * Set button loading state.
	 *
	 * @param {HTMLElement} btn Button element.
	 * @param {HTMLElement|null} labelEl Label element.
	 * @param {boolean} loading Whether loading.
	 * @param {object|null} strings String overrides for this button.
	 * @param {object|null} fallbackStrings Fallback strings.
	 */
	function setButtonLoading( btn, labelEl, loading, strings, fallbackStrings ) {
		if ( ! btn ) {
			return;
		}
		if ( loading ) {
			btn.setAttribute( 'data-loading', '1' );
			btn.disabled = true;
			if ( labelEl ) {
				labelEl.textContent = strings && strings.loading ? strings.loading : ( fallbackStrings && fallbackStrings.loading ? fallbackStrings.loading : 'Working…' );
			}
		} else {
			btn.removeAttribute( 'data-loading' );
			btn.disabled = false;
			if ( labelEl ) {
				labelEl.textContent = strings && strings.label ? strings.label : ( fallbackStrings && fallbackStrings.label ? fallbackStrings.label : 'Run' );
			}
		}
	}

	/**
	 * POST to a REST endpoint with nonce.
	 */
	function wpdFetch( url, method, body, nonceVal ) {
		var fd = new FormData();
		fd.append( 'wpd_nonce', nonceVal );
		if ( body ) {
			Object.keys( body ).forEach( function ( k ) {
				fd.append( k, body[ k ] );
			} );
		}
		var headers = {
			'X-WP-Nonce': wpRestNonce,
			'Accept': 'application/json',
		};
		var opts = {
			method: method,
			credentials: 'same-origin',
			headers: headers,
			body: fd,
		};
		return fetch( url, opts );
	}

	// --- Dashboard / Monitoring collection ---

	var collectBtn        = document.getElementById( 'wpd-collect' );
	var collectLabel      = document.getElementById( 'wpd-collect-label' );
	var collectNotice     = document.getElementById( 'wpd-collect-notice' );
	var collectRestUrl    = dashboardRestUrl || monitoringRestUrl || '';
	var collectNonce      = dashboardNonce   || monitoringNonce   || '';
	var collectStrings    = dStrings || mStrings || {};

	function triggerDashboardCollection() {
		if ( ! collectRestUrl || ! collectNonce || ! collectBtn ) {
			return;
		}
		setButtonLoading( collectBtn, collectLabel, true, collectStrings, collectStrings );
		showNotice( collectNotice, collectStrings.collecting || 'Collecting…', 'info' );

		wpdFetch( collectRestUrl, 'POST', null, collectNonce )
			.then( function ( response ) {
				if ( ! response.ok ) {
					return response.json().then( function ( error ) {
						throw new Error( ( error && error.message ) || 'Collection failed.' );
					} );
				}
				return response.json();
			} )
			.then( function ( data ) {
				setButtonLoading( collectBtn, collectLabel, false, collectStrings, collectStrings );
				var successMsg = collectStrings.success || 'Collection complete.';
				if ( data && typeof data.snapshot_id !== 'undefined' ) {
					successMsg = 'Monitoring collection completed. Snapshot #' + data.snapshot_id + ' created.';
				}
				showNotice( collectNotice, successMsg, 'success' );
				setTimeout( function () { window.location.reload(); }, 800 );
			} )
			.catch( function ( error ) {
			setButtonLoading( collectBtn, collectLabel, false, collectStrings, collectStrings );
			showNotice( collectNotice, error.message || collectStrings.error || 'Collection failed.', 'error' );
			} );
	}

	if ( collectBtn ) {
		collectBtn.addEventListener( 'click', triggerDashboardCollection );
	}

	// --- Monitoring page collection ---

	var monitorCollectBtn        = document.getElementById( 'wpd-monitor-collect' );
	var monitorCollectLabel      = document.getElementById( 'wpd-monitor-collect-label' );
	var monitorCollectNotice     = document.getElementById( 'wpd-monitor-collect-notice' );

	function triggerMonitoringCollection() {
		if ( ! monitoringRestUrl || ! monitoringNonce || ! monitorCollectBtn ) {
			return;
		}
		setButtonLoading( monitorCollectBtn, monitorCollectLabel, true, mStrings, mStrings );
		showNotice( monitorCollectNotice, mStrings.collecting || 'Collecting…', 'info' );

		wpdFetch( monitoringRestUrl, 'POST', null, monitoringNonce )
			.then( function ( response ) {
				if ( ! response.ok ) {
					return response.json().then( function ( error ) {
						throw new Error( ( error && error.message ) || 'Collection failed.' );
					} );
				}
				return response.json();
			} )
			.then( function ( data ) {
				setButtonLoading( monitorCollectBtn, monitorCollectLabel, false, mStrings, mStrings );
				var successMsg = mStrings.success || 'Collection complete.';
				if ( data && typeof data.snapshot_id !== 'undefined' ) {
					successMsg = 'Monitoring collection completed. Snapshot #' + data.snapshot_id + ' created.';
				}
				showNotice( monitorCollectNotice, successMsg, 'success' );
				setTimeout( function () { window.location.reload(); }, 800 );
			} )
			.catch( function ( error ) {
				setButtonLoading( monitorCollectBtn, monitorCollectLabel, false, mStrings, mStrings );
				showNotice( monitorCollectNotice, error.message || mStrings.error || 'Collection failed.', 'error' );
			} );
	}

	if ( monitorCollectBtn ) {
		monitorCollectBtn.addEventListener( 'click', triggerMonitoringCollection );
	}

	// --- Snapshot selection ---
	var snapshotSelect = document.getElementById( 'wpd-snapshot-select' );
	if ( snapshotSelect ) {
		snapshotSelect.addEventListener( 'change', function () {
			var id = this.value;
			if ( ! id ) {
				return;
			}
			var url = new URL( window.location.href );
			url.searchParams.set( 'snapshot', id );
			window.location.href = url.toString();
		} );
	}

	// --- Historical trend charts (Canvas API, no external deps) ---
	var chartCanvases = document.querySelectorAll( '.wpd-chart' );
	var historyDataEl = document.getElementById( 'wpd-history-data' );
	var historyData = null;
	if ( historyDataEl ) {
		try {
			historyData = JSON.parse( historyDataEl.textContent );
		} catch ( e ) {
			historyData = null;
		}
	}

	if ( chartCanvases.length && historyData ) {
		renderHistoryCharts( chartCanvases, historyData );
	}

	// --- Diagnostics ---

	var runDiagBtn        = document.getElementById( 'wpd-run-diagnostics' );
	var runDiagBtnEmpty   = document.getElementById( 'wpd-run-diagnostics-empty' );
	var runDiagLabel      = document.getElementById( 'wpd-run-diagnostics-label' );
	var diagNotice        = document.getElementById( 'wpd-diagnostics-notice' );

	function runDiagnostics() {
		if ( ! diagRestUrl || ! diagNonce ) {
			return;
		}
		var btn = runDiagBtn || runDiagBtnEmpty;
		var lbl = runDiagLabel;
		setButtonLoading( btn, lbl, true, diagStrings, diagStrings );
		showNotice( diagNotice, diagStrings.running || 'Running diagnostics…', 'info' );

		wpdFetch( diagRestUrl, 'POST', null, diagNonce )
			.then( function ( response ) {
				if ( ! response.ok ) {
					return response.json().then( function ( err ) {
						throw new Error( ( err && err.message ) || 'Diagnostics failed.' );
					} );
				}
				return response.json();
			} )
			.then( function ( data ) {
				setButtonLoading( btn, lbl, false, diagStrings, diagStrings );
				var successMsg = diagStrings.complete || 'Diagnostics complete.';
				if ( data && typeof data.rules_evaluated !== 'undefined' && typeof data.findings_count !== 'undefined' ) {
					successMsg = 'Diagnostics completed. ' + data.rules_evaluated + ' rules evaluated and ' + data.findings_count + ' findings detected.';
				}
				showNotice( diagNotice, successMsg, 'success' );
				setTimeout( function () { window.location.reload(); }, 800 );
			} )
			.catch( function ( err ) {
				setButtonLoading( btn, lbl, false, diagStrings, diagStrings );
				showNotice( diagNotice, err.message || diagStrings.error || 'Diagnostics failed.', 'error' );
			} );
	}

	if ( runDiagBtn ) {
		runDiagBtn.addEventListener( 'click', runDiagnostics );
	}
	if ( runDiagBtnEmpty ) {
		runDiagBtnEmpty.addEventListener( 'click', runDiagnostics );
	}

	// --- Finding actions (acknowledge/ignore/restore) ---

	function changeFindingStatus( btn ) {
		var id   = btn.getAttribute( 'data-id' );
		var path = 'findings/' + id + '/';
		var cls  = btn.classList;
		if ( cls.contains( 'wpd-button--ack' ) ) {
			path += 'acknowledge';
		} else if ( cls.contains( 'wpd-button--ignore' ) ) {
			path += 'ignore';
		} else if ( cls.contains( 'wpd-button--restore' ) ) {
			path += 'restore';
		} else {
			return;
		}

		var nonce = btn.getAttribute( 'data-nonce' );
		if ( ! nonce ) {
			nonce = diagNonce;
		}
		if ( ! nonce ) {
			return;
		}

		if ( ! confirm( cls.contains( 'wpd-button--ignore' ) ? ( diagStrings.confirmIgnore || 'Ignore this finding?' ) : ( cls.contains( 'wpd-button--ack' ) ? diagStrings.confirmAck || 'Acknowledge this finding?' : '' ) ) ) {
			return;
		}

		var findingsUrl = findingsRestUrl || diagRestUrl.replace( /\/run$/, '' );
		wpdFetch( findingsUrl + path, 'POST', { wpd_nonce: nonce }, nonce )
			.then( function ( response ) {
				if ( ! response.ok ) {
					return response.json().then( function ( err ) {
						throw new Error( ( err && err.message ) || 'Failed to update finding.' );
					} );
				}
				return response.json();
			} )
			.then( function () {
				setTimeout( function () { window.location.reload(); }, 400 );
			} )
			.catch( function ( err ) {
				alert( err.message || diagStrings.error || 'Failed to update finding.' );
			} );
	}

	document.querySelectorAll( '.wpd-button--ack, .wpd-button--ignore, .wpd-button--restore' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			changeFindingStatus( btn );
		} );
	} );

	// --- Finding details modal ---

	var detailModalBackdrop = document.getElementById( 'wpd-finding-modal-backdrop' );
	var detailModal         = document.getElementById( 'wpd-finding-modal' );
	var detailModalClose    = document.getElementById( 'wpd-modal-close' );
	var detailContent       = document.getElementById( 'wpd-detail-modal-content' );
	var detailTitle         = document.getElementById( 'wpd-detail-modal-title' );

	function openFindingDetails( id ) {
		if ( ! detailModal || ! detailContent ) {
			return;
		}
		var findingsUrl = findingsRestUrl || diagRestUrl.replace( /\/run$/, '' );
		if ( ! findingsUrl ) {
			return;
		}
		detailContent.innerHTML = '<div class="wpd-loading"><div class="wpd-loading-spinner"></div><div class="wpd-loading-text">' + ( diagStrings.running || 'Loading…' ) + '</div></div>';
		var backdrop = document.getElementById( 'wpd-finding-modal-backdrop' );
		if ( backdrop ) {
			backdrop.hidden = false;
			requestAnimationFrame( function () {
				backdrop.classList.add( 'wpd-modal-backdrop--visible' );
			} );
		}

		wpdFetch( findingsUrl + 'findings/' + id, 'GET', null, diagNonce )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Failed to load finding details.' );
				}
				return response.json();
			} )
			.then( function ( data ) {
				renderFindingDetails( data );
				loadFindingHistory( id );
			} )
			.catch( function () {
				detailContent.innerHTML = '<div class="wpd-notice wpd-notice--error"><p>' + ( diagStrings.error || 'Failed to load details.' ) + '</p></div>';
			} );
	}

	function loadFindingHistory( id ) {
		var findingsUrl = findingsRestUrl || diagRestUrl.replace( /\/run$/, '' );
		if ( ! findingsUrl ) {
			return;
		}
		wpdFetch( findingsUrl + 'findings/' + id + '/history', 'GET', null, diagNonce )
			.then( function ( response ) {
				if ( ! response.ok ) {
					return [];
				}
				return response.json();
			} )
			.then( function ( data ) {
				var history = data && data.history ? data.history : [];
				renderFindingHistory( history );
			} )
			.catch( function () {
				// History is optional; silently ignore failures.
			} );
	}

	function formatEvidenceValue( ev ) {
		if ( ev === null || ev === undefined ) {
			return 'Not available';
		}
		if ( typeof ev === 'object' ) {
			return JSON.stringify( ev );
		}
		return String( ev );
	}

	function renderFindingDetails( f ) {
		var ev    = f.evidence || {};
		var rem   = f.remediation || {};
		var steps = Array.isArray( rem.steps ) ? rem.steps : [];
		var html  = '';

		// --- Finding overview ---
		html += '<div class="wpd-finding-detail-overview">';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Rule ID', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value wpd-text--mono wpd-text--small">' + escapeHtml( f.rule_id || '' ) + '</span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Rule Version', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value">' + escapeHtml( f.rule_version || '' ) + '</span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Category', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value">' + escapeHtml( f.category || '' ) + '</span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Severity', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value"><span class="wpd-severity-badge wpd-severity--' + escapeHtml( f.severity || '' ) + '">' + escapeHtml( ucfirstJs( f.severity || '' ) ) + '</span></span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Confidence', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value"><span class="wpd-severity-badge wpd-severity--' + escapeHtml( f.confidence || '' ) + '">' + escapeHtml( ucfirstJs( f.confidence || '' ) ) + '</span></span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Status', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value"><span class="wpd-status-badge wpd-status-badge--small wpd-status--' + escapeHtml( f.status || '' ) + '">' + escapeHtml( ucfirstJs( f.status || '' ) ) + '</span></span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'First Detected', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value">' + escapeHtml( f.first_detected || '' ) + '</span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Last Detected', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value">' + escapeHtml( f.last_detected || '' ) + '</span></div>';
		if ( f.snapshot_id ) {
			html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Snapshot', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value">#' + escapeHtml( String( f.snapshot_id ) ) + '</span></div>';
		}
		if ( f.run_id ) {
			html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Diagnostic Run', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value">#' + escapeHtml( String( f.run_id ) ) + '</span></div>';
		}
		html += '</div>';

		// --- Problem description ---
		html += '<div class="wpd-detail-section">';
		html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'What Is the Problem?', 'wp-performance-doctor' ) ) + '</h3>';
		html += '<p>' + escapeHtml( f.description || '' ) + '</p>';
		html += '</div>';

		// --- Evidence ---
		if ( Object.keys( ev ).length ) {
			html += '<div class="wpd-detail-section">';
			html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Evidence &amp; Measurements', 'wp-performance-doctor' ) ) + '</h3>';
			html += '<table class="wpd-table wpd-table--compact"><tbody>';
			Object.keys( ev ).forEach( function ( key ) {
				var val = formatEvidenceValue( ev[ key ] );
				html += '<tr><th>' + escapeHtml( key ) + '</th><td>' + escapeHtml( val ) + '</td></tr>';
			} );
			html += '</tbody></table>';
			if ( f.snapshot_id ) {
				html += '<p class="wpd-small-text wpd-text--muted">' + escapeHtml( sprintfJs( __( 'Source: monitoring snapshot #%d.', 'wp-performance-doctor' ), f.snapshot_id ) ) + '</p>';
			}
			html += '</div>';
		}

		// --- Impact ---
		if ( f.impact ) {
			html += '<div class="wpd-detail-section">';
			html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Why Does It Matter?', 'wp-performance-doctor' ) ) + '</h3>';
			html += '<p>' + escapeHtml( f.impact || '' ) + '</p>';
			html += '</div>';
		}

		// --- Root causes ---
		var causes = rem.possible_causes || rem.root_causes || [];
		if ( Array.isArray( causes ) && causes.length ) {
			html += '<div class="wpd-detail-section">';
			html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Possible Root Causes', 'wp-performance-doctor' ) ) + '</h3>';
			html += '<ul class="wpd-small-text" style="margin: 0; padding-left: var(--wpd-space-4); list-style: disc;">';
			causes.forEach( function ( cause ) {
				html += '<li style="margin-bottom: var(--wpd-space-1);">' + escapeHtml( String( cause ) ) + '</li>';
			} );
			html += '</ul>';
			html += '<p class="wpd-small-text wpd-text--muted">' + escapeHtml( __( 'These are possible causes based on the detected evidence.', 'wp-performance-doctor' ) ) + '</p>';
			html += '</div>';
		}

		// --- Remediation steps ---
		if ( steps.length ) {
			html += '<div class="wpd-detail-section">';
			html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Recommended Action', 'wp-performance-doctor' ) ) + '</h3>';
			html += '<p>' + escapeHtml( f.recommendation || '' ) + '</p>';
			html += '<ol class="wpd-small-text" style="margin: 0; padding-left: var(--wpd-space-4); list-style: decimal;">';
			steps.forEach( function ( step ) {
				html += '<li style="margin-bottom: var(--wpd-space-1);">' + escapeHtml( String( step ) ) + '</li>';
			} );
			html += '</ol>';
			html += '</div>';
		}

		// --- Risk & difficulty ---
		html += '<div class="wpd-detail-section">';
		html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Risk &amp; Difficulty', 'wp-performance-doctor' ) ) + '</h3>';
		html += '<div class="wpd-detail-grid">';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Risk Level', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value wpd-risk-' + escapeHtml( rem.risk || 'low' ) + '">' + escapeHtml( ucfirstJs( rem.risk || '' ) ) + '</span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Difficulty', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value">' + escapeHtml( ucfirstJs( rem.difficulty || '' ) ) + '</span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Manual Action Required', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value">' + ( rem.requires_manual_action ? escapeHtml( __( 'Yes', 'wp-performance-doctor' ) ) : escapeHtml( __( 'No', 'wp-performance-doctor' ) ) ) + '</span></div>';
		html += '</div>';
		html += '</div>';

		// --- Verification ---
		html += '<div class="wpd-detail-section">';
		html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Verification', 'wp-performance-doctor' ) ) + '</h3>';
		html += '<p>' + escapeHtml( __( 'After making changes, collect a new monitoring snapshot and re-run diagnostics to verify whether this finding has been resolved.', 'wp-performance-doctor' ) ) + '</p>';
		html += '</div>';

		detailContent.innerHTML = html;
		detailTitle.textContent = escapeHtml( f.title || '' );
	}

	function renderFindingHistory( history ) {
		var container = document.getElementById( 'wpd-finding-history' );
		if ( ! container ) {
			return;
		}
		if ( ! history || ! history.length ) {
			container.innerHTML = '<p class="wpd-small-text wpd-text--muted">' + escapeHtml( __( 'No history recorded for this finding.', 'wp-performance-doctor' ) ) + '</p>';
			return;
		}
		var html = '<table class="wpd-table wpd-table--compact"><thead><tr><th>' + escapeHtml( __( 'Event', 'wp-performance-doctor' ) ) + '</th><th>' + escapeHtml( __( 'Old Status', 'wp-performance-doctor' ) ) + '</th><th>' + escapeHtml( __( 'New Status', 'wp-performance-doctor' ) ) + '</th><th>' + escapeHtml( __( 'Timestamp', 'wp-performance-doctor' ) ) + '</th></tr></thead><tbody>';
		history.forEach( function ( h ) {
			html += '<tr>';
			html += '<td>' + escapeHtml( h.action || '' ) + '</td>';
			html += '<td>' + escapeHtml( h.old_status || '—' ) + '</td>';
			html += '<td>' + escapeHtml( h.new_status || '—' ) + '</td>';
			html += '<td>' + escapeHtml( h.created_at || '' ) + '</td>';
			html += '</tr>';
		} );
		html += '</tbody></table>';
		container.innerHTML = html;
	}

	function escapeHtml( text ) {
		if ( typeof text !== 'string' ) {
			return '';
		}
		return text
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function ucfirstJs( str ) {
		if ( ! str ) return str;
		return str.charAt( 0).toUpperCase() + str.slice( 1 );
	}

	function sprintfJs( fmt ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		return fmt.replace( /%(\$?\d+)?([sd])/g, function ( match, num, type ) {
			var arg = args.shift();
			return arg !== undefined ? String( arg ) : match;
		} );
	}

	// Finding details links.
	document.querySelectorAll( '.wpd-finding-link, .wpd-button--details' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var id = link.getAttribute( 'data-id' );
			if ( id ) {
				openFindingDetails( id );
			}
		} );
	} );

	// Modal close.
	function closeModal() {
		if ( detailModalBackdrop ) {
			detailModalBackdrop.classList.remove( 'wpd-modal-backdrop--visible' );
			setTimeout( function () {
				detailModalBackdrop.hidden = true;
			}, 200 );
		}
	}

	if ( detailModalClose ) {
		detailModalClose.addEventListener( 'click', closeModal );
	}
	if ( detailModalBackdrop ) {
		detailModalBackdrop.addEventListener( 'click', function ( e ) {
			if ( e.target === detailModalBackdrop ) {
				closeModal();
			}
		} );
	}

	// --- Finding table filtering ---

	var filterSeverity    = document.getElementById( 'wpd-filter-severity' );
	var filterCategory    = document.getElementById( 'wpd-filter-category' );
	var filterStatus      = document.getElementById( 'wpd-filter-status' );
	var filterSearch      = document.getElementById( 'wpd-filter-search' );
	var findingRows       = document.querySelectorAll( '.wpd-finding-row' );

	function filterFindings() {
		var sev = filterSeverity ? filterSeverity.value : '';
		var cat = filterCategory ? filterCategory.value : '';
		var st  = filterStatus  ? filterStatus.value  : '';
		var q   = filterSearch  ? filterSearch.value.toLowerCase()  : '';

		findingRows.forEach( function ( row ) {
			var show = true;

			if ( sev ) {
				var sevBadge = row.querySelector( '.wpd-severity-badge' );
				if ( sevBadge && ! sevBadge.textContent.toLowerCase().includes( sev ) ) {
					show = false;
				}
			}
			if ( cat ) {
				var catCell = row.querySelector( '.wpd-finding-category' );
				if ( catCell && ! catCell.textContent.toLowerCase().includes( cat ) ) {
					show = false;
				}
			}
			if ( st ) {
				var statusBadge = row.querySelector( '.wpd-status-badge' );
				if ( statusBadge && ! statusBadge.textContent.toLowerCase().includes( st ) ) {
					show = false;
				}
			}
			if ( q ) {
				var title = row.querySelector( '.wpd-finding-title' );
				if ( title && ! title.textContent.toLowerCase().includes( q ) ) {
					show = false;
				}
			}

			row.hidden = ! show;
		} );
	}

	if ( filterSeverity ) {
		filterSeverity.addEventListener( 'change', filterFindings );
	}
	if ( filterCategory ) {
		filterCategory.addEventListener( 'change', filterFindings );
	}
	if ( filterStatus ) {
		filterStatus.addEventListener( 'change', filterFindings );
	}
	if ( filterSearch ) {
		filterSearch.addEventListener( 'input', filterFindings );
	}

	// --- Historical trend charts ---
	/**
	 * Render a compact sparkline chart for a metric's history.
	 *
	 * @param {HTMLElement} container Chart container element.
	 * @param {Array} points Array of { timestamp, metric_value, unit }.
	 * @param {string} unit Unit label.
	 */
	function renderSparkline( container, points, unit ) {
		if ( ! container || ! points || points.length < 2 ) {
			container.innerHTML = '<div class="wpd-chart-empty">Insufficient history</div>';
			return;
		}

		var canvas = document.createElement( 'canvas' );
		canvas.width = container.clientWidth || 280;
		canvas.height = 120;
		canvas.style.display = 'block';
		container.innerHTML = '';
		container.appendChild( canvas );

		var ctx = canvas.getContext( '2d' );
		var width = canvas.width;
		var height = canvas.height;
		var padding = { top: 8, right: 8, bottom: 16, left: 28 };

		// Extract numeric values.
		var values = points.map( function ( p ) { return parseFloat( p.metric_value ); } );
		var min = Math.min.apply( null, values );
		var max = Math.max.apply( null, values );
		var range = max - min || 1;

		var maxLabel = ( max / 1048576 ).toFixed( 1 ) + ' MB';
		if ( unit === 'seconds' ) {
			maxLabel = max.toFixed( 2 ) + ' s';
		}

		ctx.clearRect( 0, 0, width, height );

		// Axis labels.
		ctx.fillStyle = '#94a3b8';
		ctx.font = '10px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
		ctx.textAlign = 'right';
		ctx.fillText( maxLabel, padding.left - 6, padding.top + 10 );
		ctx.fillText( ( min / 1048576 ).toFixed( 1 ) + ' MB', padding.left - 6, height - padding.bottom + 8 );
		if ( unit === 'seconds' ) {
			ctx.fillText( min.toFixed( 2 ) + ' s', padding.left - 6, height - padding.bottom + 8 );
		}

		// Draw line.
		var plotW = width - padding.left - padding.right;
		var plotH = height - padding.top - padding.bottom;
		var step = plotW / ( points.length - 1 );

		ctx.beginPath();
		ctx.strokeStyle = '#0f62fe';
		ctx.lineWidth = 2;
		ctx.lineJoin = 'round';
		ctx.lineCap = 'round';

		points.forEach( function ( p, i ) {
			var x = padding.left + i * step;
			var y = height - padding.bottom - ( ( parseFloat( p.metric_value ) - min ) / range ) * plotH;
			if ( i === 0 ) {
				ctx.moveTo( x, y );
			} else {
				ctx.lineTo( x, y );
			}
		} );
		ctx.stroke();

		// Points.
		ctx.fillStyle = '#ffffff';
		ctx.strokeStyle = '#0f62fe';
		ctx.lineWidth = 2;
		points.forEach( function ( p, i ) {
			var x = padding.left + i * step;
			var y = height - padding.bottom - ( ( parseFloat( p.metric_value ) - min ) / range ) * plotH;
			ctx.beginPath();
			ctx.arc( x, y, 3, 0, 2 * Math.PI );
			ctx.fill();
			ctx.stroke();
		} );
	}

	/**
	 * Render all history charts from embedded JSON data.
	 *
	 * @param {NodeList} containers Chart container elements.
	 * @param {object} data Parsed history data.
	 */
	function renderHistoryCharts( containers, data ) {
		var seriesMap = {
			'system.memory_usage': data.memory || [],
			'database.database_size': data.database || [],
			'system.peak_memory_usage': data.peak || [],
		};

		containers.forEach( function ( container ) {
			var metric = container.getAttribute( 'data-metric' );
			var points = seriesMap[ metric ] || [];
			var unit = 'bytes';
			if ( metric && metric.indexOf( 'duration' ) !== -1 ) {
				unit = 'seconds';
			}
			renderSparkline( container, points, unit );
		} );
	}

	// --- Recommendations detail panel ---

	var recModalBackdrop = document.getElementById( 'wpd-rec-detail-backdrop' );
	var recModal         = document.getElementById( 'wpd-rec-detail-panel' );
	var recModalClose    = document.getElementById( 'wpd-rec-detail-close' );
	var recContent       = document.getElementById( 'wpd-rec-detail-body' );
	var recTitle         = document.getElementById( 'wpd-rec-detail-title' );
	var recData          = window.wpdRecommendationsPage && window.wpdRecommendationsPage.recommendations ? window.wpdRecommendationsPage.recommendations : [];

	function findRecommendationById( id ) {
		if ( ! recData || ! Array.isArray( recData ) ) {
			return null;
		}
		for ( var i = 0; i < recData.length; i++ ) {
			if ( recData[ i ] && ( recData[ i ].id === id || recData[ i ].rule_id === id ) ) {
				return recData[ i ];
			}
		}
		return null;
	}

	function openRecommendationDetails( id ) {
		if ( ! recModal || ! recContent ) {
			return;
		}
		var rec = findRecommendationById( id );
		if ( ! rec ) {
			return;
		}

		var ev = rec.evidence || {};
		var rem = rec;
		var steps = Array.isArray( rec.steps ) ? rec.steps : [];
		var causes = rec.possible_causes || rec.root_causes || [];

		var html = '';
		html += '<div class="wpd-rec-detail-overview">';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Rule ID', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value wpd-text--mono wpd-text--small">' + escapeHtml( rec.rule_id || '' ) + '</span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Category', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value">' + escapeHtml( rec.category || '' ) + '</span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Severity', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value"><span class="wpd-severity-badge wpd-severity--' + escapeHtml( rec.severity || '' ) + '">' + escapeHtml( ucfirstJs( rec.severity || '' ) ) + '</span></span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Confidence', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value"><span class="wpd-severity-badge wpd-severity--' + escapeHtml( rec.confidence || '' ) + '">' + escapeHtml( ucfirstJs( rec.confidence || '' ) ) + '</span></span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Difficulty', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value">' + escapeHtml( ucfirstJs( rec.difficulty || '' ) ) + '</span></div>';
		html += '<div class="wpd-detail-field"><span class="wpd-detail-field-label">' + escapeHtml( __( 'Risk Level', 'wp-performance-doctor' ) ) + '</span><span class="wpd-detail-field-value wpd-risk-' + escapeHtml( rec.risk || 'low' ) + '">' + escapeHtml( ucfirstJs( rec.risk || '' ) ) + '</span></div>';
		html += '</div>';

		html += '<div class="wpd-detail-section">';
		html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Problem Summary', 'wp-performance-doctor' ) ) + '</h3>';
		html += '<p>' + escapeHtml( rec.problem || '' ) + '</p>';
		html += '</div>';

		if ( Object.keys( ev ).length ) {
			html += '<div class="wpd-detail-section">';
			html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Supporting Evidence', 'wp-performance-doctor' ) ) + '</h3>';
			html += '<table class="wpd-table wpd-table--compact"><tbody>';
			Object.keys( ev ).forEach( function ( key ) {
				html += '<tr><th>' + escapeHtml( key ) + '</th><td>' + escapeHtml( formatEvidenceValue( ev[ key ] ) ) + '</td></tr>';
			} );
			html += '</tbody></table>';
			html += '</div>';
		}

		html += '<div class="wpd-detail-section">';
		html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Why It Matters', 'wp-performance-doctor' ) ) + '</h3>';
		html += '<p>' + escapeHtml( rec.why_matters || '' ) + '</p>';
		html += '</div>';

		if ( causes.length ) {
			html += '<div class="wpd-detail-section">';
			html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Possible Root Causes', 'wp-performance-doctor' ) ) + '</h3>';
			html += '<ul class="wpd-small-text" style="margin: 0; padding-left: var(--wpd-space-4); list-style: disc;">';
			causes.forEach( function ( cause ) {
				html += '<li style="margin-bottom: var(--wpd-space-1);">' + escapeHtml( String( cause ) ) + '</li>';
			} );
			html += '</ul>';
			html += '</div>';
		}

		if ( steps.length ) {
			html += '<div class="wpd-detail-section">';
			html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Implementation Steps', 'wp-performance-doctor' ) ) + '</h3>';
			html += '<ol class="wpd-small-text" style="margin: 0; padding-left: var(--wpd-space-4); list-style: decimal;">';
			steps.forEach( function ( step ) {
				html += '<li style="margin-bottom: var(--wpd-space-1);">' + escapeHtml( String( step ) ) + '</li>';
			} );
			html += '</ol>';
			html += '</div>';
		}

		html += '<div class="wpd-detail-section">';
		html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Expected Benefit', 'wp-performance-doctor' ) ) + '</h3>';
		html += '<p>' + escapeHtml( rec.expected_benefit || '' ) + '</p>';
		html += '</div>';

		html += '<div class="wpd-detail-section">';
		html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Verification Steps', 'wp-performance-doctor' ) ) + '</h3>';
		html += '<p>' + escapeHtml( __( 'After making changes, collect a new monitoring snapshot and re-run diagnostics. Verify that the finding status changes to resolved or that the measured value improves.', 'wp-performance-doctor' ) ) + '</p>';
		html += '</div>';

		if ( rec.related_finding_id ) {
			html += '<div class="wpd-detail-section" style="margin-top: var(--wpd-space-6); border-top: 1px solid var(--wpd-color-border); padding-top: var(--wpd-space-4);">';
			html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Related Finding', 'wp-performance-doctor' ) ) + '</h3>';
			html += '<p>' + escapeHtml( sprintfJs( __( 'Finding #%d — view its full details and history on the Diagnostics page.', 'wp-performance-doctor' ), rec.related_finding_id ) ) + '</p>';
			html += '<a href="?page=wp-performance-doctor-diagnostics" class="wpd-button wpd-button--small wpd-button--outline">' + escapeHtml( __( 'View Finding', 'wp-performance-doctor' ) ) + '</a>';
			html += '</div>';
		}

		recContent.innerHTML = html;
		recTitle.textContent = escapeHtml( rec.title || '' );

		var backdrop = document.getElementById( 'wpd-rec-detail-backdrop' );
		if ( backdrop ) {
			backdrop.hidden = false;
			requestAnimationFrame( function () {
				backdrop.classList.add( 'wpd-modal-backdrop--visible' );
			} );
		}
	}

	function closeRecModal() {
		if ( recModalBackdrop ) {
			recModalBackdrop.classList.remove( 'wpd-modal-backdrop--visible' );
			setTimeout( function () {
				recModalBackdrop.hidden = true;
			}, 200 );
		}
	}

	document.querySelectorAll( '.wpd-rec-detail-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var id = btn.getAttribute( 'data-rec-id' );
			if ( id ) {
				openRecommendationDetails( id );
			}
		} );
	} );

	if ( recModalClose ) {
		recModalClose.addEventListener( 'click', closeRecModal );
	}
	if ( recModalBackdrop ) {
		recModalBackdrop.addEventListener( 'click', function ( e ) {
			if ( e.target === recModalBackdrop ) {
				closeRecModal();
			}
		} );
	}

	// --- Performance ---

	var performance = window.wpdPerformance || {};
	var perfRestUrl   = performance.restUrl   || '';
	var perfNonce     = performance.nonce   || '';
	var perfStrings   = performance.strings || {};

	// Read inline JSON config for performance URLs.
	var perfConfigEl = document.getElementById( 'wpd-performance-config' );
	if ( perfConfigEl ) {
		try {
			var perfCfg = JSON.parse( perfConfigEl.textContent );
			perfRestUrl = perfCfg.restUrl || perfRestUrl;
			perfNonce   = perfCfg.nonce || perfNonce;
			wpRestNonce = perfCfg.wpRestNonce || wpRestNonce;
		} catch ( e ) {}
	}

	var runProfileBtn        = document.getElementById( 'wpd-run-profile' );
	var runProfileBtnEmpty   = document.getElementById( 'wpd-run-profile-empty' );
	var runProfileLabel      = document.getElementById( 'wpd-run-profile-label' );
	var perfNotice           = document.getElementById( 'wpd-performance-notice' );

	function runProfile() {
		if ( ! perfRestUrl || ! perfNonce ) {
			return;
		}
		var btn = runProfileBtn || runProfileBtnEmpty;
		var lbl = runProfileLabel;
		setButtonLoading( btn, lbl, true, perfStrings, perfStrings );
		showNotice( perfNotice, perfStrings.profiling || 'Profiling…', 'info' );

		wpdFetch( perfRestUrl, 'POST', null, perfNonce )
			.then( function ( response ) {
				if ( ! response.ok ) {
					return response.json().then( function ( err ) {
						throw new Error( ( err && err.message ) || 'Profiling failed.' );
					} );
				}
				return response.json();
			} )
			.then( function ( data ) {
				setButtonLoading( btn, lbl, false, perfStrings, perfStrings );
				var successMsg = perfStrings.complete || 'Profile complete.';
				if ( data && data.snapshot_id ) {
					successMsg = 'Profile complete. Snapshot #' + data.snapshot_id + ' created.';
				}
				showNotice( perfNotice, successMsg, 'success' );
				setTimeout( function () { window.location.reload(); }, 800 );
			} )
			.catch( function ( err ) {
				setButtonLoading( btn, lbl, false, perfStrings, perfStrings );
				showNotice( perfNotice, err.message || perfStrings.error || 'Profiling failed.', 'error' );
			} );
	}

	if ( runProfileBtn ) {
		runProfileBtn.addEventListener( 'click', runProfile );
	}
	if ( runProfileBtnEmpty ) {
		runProfileBtnEmpty.addEventListener( 'click', runProfile );
	}

	// --- Bottleneck steps modal ---

	var stepsModalBackdrop = document.getElementById( 'wpd-bottleneck-steps-backdrop' );
	var stepsModal         = document.getElementById( 'wpd-bottleneck-steps-modal' );
	var stepsModalClose    = document.getElementById( 'wpd-bottleneck-steps-close' );
	var stepsContent       = document.getElementById( 'wpd-bottleneck-steps-body' );
	var stepsTitle         = document.getElementById( 'wpd-bottleneck-steps-title' );

	function openBottleneckSteps( btn ) {
		if ( ! stepsModal || ! stepsContent ) {
			return;
		}
		var stepsJson = btn.getAttribute( 'data-steps' );
		if ( ! stepsJson ) {
			return;
		}
		try {
			var steps = JSON.parse( stepsJson );
		} catch ( e ) {
			return;
		}

		var html = '<ol class="wpd-bottleneck-steps-list" style="margin: 0; padding-left: var(--wpd-space-4);">';
		for ( var i = 0; i < steps.length; i++ ) {
			html += '<li style="margin-bottom: var(--wpd-space-2);">' + escapeHtml( String( steps[ i ] ) ) + '</li>';
		}
		html += '</ol>';
		stepsContent.innerHTML = html;

		var backdrop = document.getElementById( 'wpd-bottleneck-steps-backdrop' );
		if ( backdrop ) {
			backdrop.hidden = false;
			requestAnimationFrame( function () {
				backdrop.classList.add( 'wpd-modal-backdrop--visible' );
			} );
		}
	}

	function closeStepsModal() {
		if ( stepsModalBackdrop ) {
			stepsModalBackdrop.classList.remove( 'wpd-modal-backdrop--visible' );
			setTimeout( function () {
				stepsModalBackdrop.hidden = true;
			}, 200 );
		}
	}

	document.querySelectorAll( '.wpd-bottleneck-steps' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			openBottleneckSteps( btn );
		} );
	} );

	if ( stepsModalClose ) {
		stepsModalClose.addEventListener( 'click', closeStepsModal );
	}
	if ( stepsModalBackdrop ) {
		stepsModalBackdrop.addEventListener( 'click', function ( e ) {
			if ( e.target === stepsModalBackdrop ) {
				closeStepsModal();
			}
		} );
	}

	// --- Browser performance profiling ---

	var perfSessionId   = document.getElementById( 'wpd-perf-session-id' );
	var perfStartBtn    = document.getElementById( 'wpd-run-frontend-profile' );
	var perfStartLabel  = document.getElementById( 'wpd-run-frontend-profile-label' );
	var perfNotice      = document.getElementById( 'wpd-frontend-profile-notice' );
	var perfBrowserUrl  = performance && performance.restBrowserUrl ? performance.restBrowserUrl : '';
	var perfBrowserNonce = performance && performance.browserNonce ? performance.browserNonce : '';

	// Read inline JSON config for browser metrics URL.
	var browserConfigEl = document.getElementById( 'wpd-browser-metrics-config' );
	if ( browserConfigEl ) {
		try {
			var browserCfg = JSON.parse( browserConfigEl.textContent );
			perfBrowserUrl  = browserCfg.restUrl  || perfBrowserUrl;
			perfBrowserNonce = browserCfg.nonce   || perfBrowserNonce;
		} catch ( e ) {}
	}

	// --- Browser measurement collector ---

	/**
	 * Report which browser profiling APIs are supported.
	 * @return {object} Browser support metric data.
	 */
	function collectBrowserSupport() {
		var support = {
			navigation_timing: ! ! ( window.performance && window.performance.getEntriesByType ),
			resource_timing: ! ! ( window.performance && window.performance.getEntriesByType ),
			performance_observer: ! ! window.PerformanceObserver,
			lcp: !1,
			cls: !1,
			inp: !1,
		};
		try {
			if ( window.PerformanceObserver ) {
				var probe = new PerformanceObserver( function () {} );
				probe.observe( { entryTypes: [ 'largest-contentful-paint' ] } );
				support.lcp = !0;
				probe.disconnect();
			}
		} catch ( e ) {}
		try {
			if ( window.PerformanceObserver ) {
				var probe = new PerformanceObserver( function () {} );
				probe.observe( { entryTypes: [ 'layout-shift' ] } );
				support.cls = !0;
				probe.disconnect();
			}
		} catch ( e ) {}
		try {
			if ( window.PerformanceObserver ) {
				var probe = new PerformanceObserver( function () {} );
				probe.observe( { entryTypes: [ 'event' ] } );
				support.inp = !0;
				probe.disconnect();
			}
		} catch ( e ) {}
		var all = support.navigation_timing && support.resource_timing && support.performance_observer;
		return {
			'frontend.browser_support': {
				value: all ? 'supported' : 'partial',
				unit: 'status',
				category: 'frontend',
				availability_status: 'available',
				metadata: { support: support }
			}
		};
	}

	/**
	 * Collect navigation timing metrics from the browser.
	 * Only collected once the document has finished loading so navigation
	 * timing entries are complete.
	 * @return {object} Metric data keyed by metric identifier.
	 */
	function collectNavigationTiming() {
		if ( ! window.performance || ! window.performance.getEntriesByType ) {
			return {};
		}
		if ( document.readyState !== 'complete' ) {
			return {};
		}

		var navEntries = window.performance.getEntriesByType( 'navigation' );
		if ( ! navEntries || navEntries.length === 0 ) {
			return {};
		}

		var nav = navEntries[ 0 ];
		var metrics = {};

		// DNS lookup duration.
		if ( typeof nav.domainLookupStart === 'number' && typeof nav.domainLookupEnd === 'number' ) {
			var dns = nav.domainLookupEnd - nav.domainLookupStart;
			if ( dns >= 0 ) {
				metrics[ 'frontend.navigation_timing_dns' ] = {
					value: dns,
					unit: 'ms',
					category: 'frontend',
					availability_status: 'available',
					metadata: {}
				};
			}
		}

		// TCP connection duration.
		if ( typeof nav.connectStart === 'number' && typeof nav.connectEnd === 'number' ) {
			var tcp = nav.connectEnd - nav.connectStart;
			if ( tcp >= 0 ) {
				metrics[ 'frontend.navigation_timing_tcp' ] = {
					value: tcp,
					unit: 'ms',
					category: 'frontend',
					availability_status: 'available',
					metadata: {}
				};
			}
		}

		// TLS negotiation duration.
		if ( typeof nav.secureConnectionStart === 'number' && typeof nav.connectEnd === 'number' && nav.secureConnectionStart > 0 ) {
			var tls = nav.connectEnd - nav.secureConnectionStart;
			if ( tls >= 0 ) {
				metrics[ 'frontend.navigation_timing_tls' ] = {
					value: tls,
					unit: 'ms',
					category: 'frontend',
					availability_status: 'available',
					metadata: {}
				};
			}
		}

		// Time to First Byte.
		if ( typeof nav.responseStart === 'number' && typeof nav.requestStart === 'number' ) {
			var ttfb = nav.responseStart - nav.requestStart;
			if ( ttfb >= 0 ) {
				metrics[ 'frontend.navigation_timing_ttfb' ] = {
					value: ttfb,
					unit: 'ms',
					category: 'frontend',
					availability_status: 'available',
					metadata: {}
				};
			}
		}

		// Response download duration.
		if ( typeof nav.responseEnd === 'number' && typeof nav.responseStart === 'number' ) {
			var download = nav.responseEnd - nav.responseStart;
			if ( download >= 0 ) {
				metrics[ 'frontend.navigation_timing_response_download' ] = {
					value: download,
					unit: 'ms',
					category: 'frontend',
					availability_status: 'available',
					metadata: {}
				};
			}
		}

		// DOM interactive.
		if ( typeof nav.domInteractive === 'number' && typeof nav.navigationStart === 'number' ) {
			metrics[ 'frontend.navigation_timing_dom_interactive' ] = {
				value: nav.domInteractive - nav.navigationStart,
				unit: 'ms',
				category: 'frontend',
				availability_status: 'available',
				metadata: {}
			};
		}

		// DOM Content Loaded.
		if ( typeof nav.domContentLoadedEventEnd === 'number' && typeof nav.navigationStart === 'number' ) {
			metrics[ 'frontend.navigation_timing_dom_content_loaded' ] = {
				value: nav.domContentLoadedEventEnd - nav.navigationStart,
				unit: 'ms',
				category: 'frontend',
				availability_status: 'available',
				metadata: {}
			};
		}

		// DOM Complete.
		if ( typeof nav.domComplete === 'number' && typeof nav.navigationStart === 'number' ) {
			metrics[ 'frontend.navigation_timing_dom_complete' ] = {
				value: nav.domComplete - nav.navigationStart,
				unit: 'ms',
				category: 'frontend',
				availability_status: 'available',
				metadata: {}
			};
		}

		// Full page load.
		if ( typeof nav.loadEventEnd === 'number' && typeof nav.navigationStart === 'number' ) {
			metrics[ 'frontend.navigation_timing_page_load' ] = {
				value: nav.loadEventEnd - nav.navigationStart,
				unit: 'ms',
				category: 'frontend',
				availability_status: 'available',
				metadata: {}
			};
		}

		// Redirect duration.
		if ( typeof nav.redirectEnd === 'number' && typeof nav.redirectStart === 'number' && nav.redirectEnd > 0 ) {
			var redirect = nav.redirectEnd - nav.redirectStart;
			if ( redirect >= 0 ) {
				metrics[ 'frontend.navigation_timing_redirect' ] = {
					value: redirect,
					unit: 'ms',
					category: 'frontend',
					availability_status: 'available',
					metadata: {}
				};
			}
		}

		return metrics;
	}

	/**
	 * Collect resource timing metrics from the browser.
	 * @return {object} Metric data keyed by metric identifier.
	 */
	function collectResourceTiming() {
		if ( ! window.performance || ! window.performance.getEntriesByType ) {
			return {};
		}

		var resources = window.performance.getEntriesByType( 'resource' );
		if ( ! resources || resources.length === 0 ) {
			return {};
		}

		var total      = resources.length;
		var cssCount   = 0;
		var jsCount    = 0;
		var imageCount = 0;
		var fontCount  = 0;
		var external   = 0;
		var totalSize  = 0;
		var hasSize    = false;
		var maxDuration = 0;
		var slowestUrl = '';
		var urls       = [];

		resources.forEach( function ( res ) {
			var init = ( res.initiatorType || '' ).toLowerCase();
			if ( init === 'css' || init === 'link' ) {
				cssCount++;
			} else if ( init === 'script' ) {
				jsCount++;
			} else if ( init === 'img' ) {
				imageCount++;
			} else if ( init === 'font' ) {
				fontCount++;
			}

			// Check external (different origin from current page).
			try {
				var resUrl = new URL( res.name || '' );
				var curUrl = new URL( window.location.href );
				if ( resUrl.hostname !== curUrl.hostname ) {
					external++;
				}
				if ( urls.length < 20 ) {
					urls.push( resUrl.origin + resUrl.pathname );
				}
			} catch ( e ) {}

			// Transfer size.
			if ( typeof res.transferSize === 'number' && res.transferSize >= 0 ) {
				totalSize += res.transferSize;
				hasSize = true;
			}

			// Duration.
			if ( typeof res.duration === 'number' && res.duration > maxDuration ) {
				maxDuration = res.duration;
				slowestUrl = res.name || '';
			}
		} );

		var metrics = {
			'frontend.resource_count': {
				value: total,
				unit: 'count',
				category: 'frontend',
				availability_status: 'available',
				metadata: {}
			},
			'frontend.css_resource_count': {
				value: cssCount,
				unit: 'count',
				category: 'frontend',
				availability_status: 'available',
				metadata: {}
			},
			'frontend.js_resource_count': {
				value: jsCount,
				unit: 'count',
				category: 'frontend',
				availability_status: 'available',
				metadata: {}
			},
			'frontend.image_resource_count': {
				value: imageCount,
				unit: 'count',
				category: 'frontend',
				availability_status: 'available',
				metadata: {}
			},
			'frontend.font_resource_count': {
				value: fontCount,
				unit: 'count',
				category: 'frontend',
				availability_status: 'available',
				metadata: {}
			},
			'frontend.external_resource_count': {
				value: external,
				unit: 'count',
				category: 'frontend',
				availability_status: 'available',
				metadata: {}
			},
			'frontend.resource_loading_duration': {
				value: maxDuration,
				unit: 'ms',
				category: 'frontend',
				availability_status: 'available',
				metadata: {}
			}
		};

		if ( hasSize ) {
			metrics[ 'frontend.resource_transfer_size' ] = {
				value: totalSize,
				unit: 'bytes',
				category: 'frontend',
				availability_status: 'available',
				metadata: {}
			};
		}

		if ( urls.length > 0 ) {
			metrics[ 'frontend.resource_transfer_size' ].metadata = {
				resource_urls: urls
			};
		}

		return metrics;
	}

	/**
	 * Collect Core Web Vitals using PerformanceObserver.
	 * @return {Promise<object>} Promise resolving to metric data.
	 */
	function collectCoreWebVitals() {
		return new Promise( function ( resolve ) {
			var metrics = {};
			var collected = 0;
			var expected  = 3; // LCP, CLS, INP

			function markDone() {
				collected++;
				if ( collected >= expected ) {
					resolve( metrics );
				}
			}

			// Largest Contentful Paint.
			if ( typeof PerformanceObserver !== 'undefined' ) {
				try {
					var lcpObserver = new PerformanceObserver( function ( list ) {
						var entries = list.getEntries();
						var last    = entries[ entries.length - 1 ];
						if ( last && typeof last.startTime === 'number' ) {
							metrics[ 'frontend.largest_contentful_paint' ] = {
								value: last.startTime,
								unit: 'ms',
								category: 'frontend',
								availability_status: 'available',
								metadata: {}
							};
						}
						markDone();
					} );
					lcpObserver.observe( { entryTypes: [ 'largest-contentful-paint' ] } );
				} catch ( e ) {
					markDone();
				}
			} else {
				markDone();
			}

			// Cumulative Layout Shift.
			try {
				var clsObserver = new PerformanceObserver( function ( list ) {
					var clsValue = 0;
					list.getEntries().forEach( function ( entry ) {
						if ( ! entry.hadRecentInput ) {
							clsValue += entry.value;
						}
					} );
					if ( clsValue > 0 ) {
						metrics[ 'frontend.cumulative_layout_shift' ] = {
							value: clsValue,
							unit: 'score',
							category: 'frontend',
							availability_status: 'available',
							metadata: {}
						};
					}
					markDone();
				} );
				clsObserver.observe( { entryTypes: [ 'layout-shift' ] } );
			} catch ( e ) {
				markDone();
			}

			// Interaction to Next Paint.
			try {
				var inpObserver = new PerformanceObserver( function ( list ) {
					var entries = list.getEntries();
					var maxInp  = 0;
					entries.forEach( function ( entry ) {
						if ( typeof entry.duration === 'number' && entry.duration > maxInp ) {
							maxInp = entry.duration;
						}
					} );
					if ( maxInp > 0 ) {
						metrics[ 'frontend.interaction_to_next_paint' ] = {
							value: maxInp,
							unit: 'ms',
							category: 'frontend',
							availability_status: 'available',
							metadata: {}
						};
					}
					markDone();
				} );
				inpObserver.observe( { entryTypes: [ 'event' ] } );
			} catch ( e ) {
				markDone();
			}

			// Timeout after 5 seconds to avoid hanging.
			setTimeout( function () {
				resolve( metrics );
			}, 5000 );
		} );
	}

	/**
	 * Collect all browser performance metrics.
	 * @return {Promise<object>} All collected metrics.
	 */
	async function collectAllBrowserMetrics() {
		var navMetrics = collectNavigationTiming();
		var resMetrics = collectResourceTiming();
		var cwvMetrics = await collectCoreWebVitals();
		var support    = collectBrowserSupport();

		return Object.assign( {}, navMetrics, resMetrics, cwvMetrics, support );
	}

	/**
	 * Submit browser metrics to the REST endpoint.
	 * @param {object} metrics Metric data.
	 * @param {number} sessionId Profiling session ID.
	 * @param {number} snapshotId Snapshot ID.
	 * @return {Promise<object>} REST response.
	 */
	function submitBrowserMetrics( metrics, sessionId, snapshotId ) {
		if ( ! perfBrowserUrl || ! perfBrowserNonce ) {
			return Promise.reject( new Error( 'Browser metrics endpoint not configured.' ) );
		}

		var body = new FormData();
		body.append( 'wpd_nonce', perfBrowserNonce );
		body.append( 'session_id', sessionId );
		body.append( 'snapshot_id', snapshotId );
		body.append( 'metrics', JSON.stringify( metrics ) );

		var headers = {
			'X-WP-Nonce': wpRestNonce,
			'Accept': 'application/json'
		};

		return fetch( perfBrowserUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: headers,
			body: body
		} );
	}

	// --- Frontend profile workflow ---

	function runFrontendProfile() {
		if ( ! perfBrowserUrl || ! perfBrowserNonce || ! perfStartBtn ) {
			return;
		}

		var sessionId = perfSessionId ? parseInt( perfSessionId.value, 10 ) : 0;
		var snapshotId = document.getElementById( 'wpd-perf-snapshot-id' );
		snapshotId = snapshotId ? parseInt( snapshotId.value, 10 ) : 0;
		var targetUrlEl = document.getElementById( 'wpd-perf-target-url' );
		var contextEl   = document.getElementById( 'wpd-perf-context' );
		var targetUrl   = targetUrlEl ? targetUrlEl.value : '';
		var context     = contextEl ? contextEl.value : 'admin';

		if ( ! sessionId ) {
			showNotice( perfNotice, perfStrings.noSession || 'No active profiling session. Run a profile first.', 'error' );
			return;
		}

		setButtonLoading( perfStartBtn, perfStartLabel, true, {}, {} );
		showNotice( perfNotice, 'Collecting browser metrics…', 'info' );

		collectAllBrowserMetrics()
			.then( function ( metrics ) {
				if ( Object.keys( metrics ).length === 0 ) {
					throw new Error( 'No browser metrics could be collected.' );
				}
				// Tag every metric with the measured page URL and context so
				// results are never presented without identifying what was
				// measured.
				Object.keys( metrics ).forEach( function ( key ) {
					metrics[ key ].page_url      = targetUrl;
					metrics[ key ].measurement_context = context;
				} );
				return submitBrowserMetrics( metrics, sessionId, snapshotId );
			} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					return response.json().then( function ( err ) {
						throw new Error( ( err && err.message ) || 'Failed to submit browser metrics.' );
					} );
				}
				return response.json();
			} )
			.then( function ( data ) {
				setButtonLoading( perfStartBtn, perfStartLabel, false, {}, {} );
				var msg = 'Browser metrics submitted (' + ( data.stored || 0 ) + ' metrics).';
				showNotice( perfNotice, msg, 'success' );
				setTimeout( function () { window.location.reload(); }, 800 );
			} )
			.catch( function ( err ) {
				setButtonLoading( perfStartBtn, perfStartLabel, false, {}, {} );
				showNotice( perfNotice, err.message || 'Browser profiling failed.', 'error' );
			} );
	}

	if ( perfStartBtn ) {
		perfStartBtn.addEventListener( 'click', runFrontendProfile );
	}

} )();

