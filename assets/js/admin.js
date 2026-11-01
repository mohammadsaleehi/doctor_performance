/* admin.js — WP Performance Doctor admin interactivity */

( function () {
	'use strict';

	// --- Shared state ---
	var dashboard   = window.wpdDashboard   || {};
	var monitoring  = window.wpdMonitoring  || {};
	var diagnostics = window.wpdDiagnostics || {};

	var dashboardRestUrl = dashboard.restUrl   || '';
	var dashboardNonce   = dashboard.nonce   || '';
	var monitoringRestUrl = monitoring.restUrl  || '';
	var monitoringNonce   = monitoring.nonce   || '';
	var wpRestNonce      = dashboard.wpRestNonce || monitoring.wpRestNonce || diagnostics.wpRestNonce || '';
	var dStrings         = dashboard.strings   || {};
	var mStrings         = monitoring.strings   || {};
	var diagStrings      = diagnostics.strings || {};
	var diagRestUrl      = diagnostics.restUrl || '';
	var diagNonce        = diagnostics.nonce   || '';

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
	 */
	function setButtonLoading( btn, labelEl, loading ) {
		if ( ! btn ) {
			return;
		}
		if ( loading ) {
			btn.setAttribute( 'data-loading', '1' );
			btn.disabled = true;
			if ( labelEl ) {
				labelEl.textContent = ( diagStrings && diagStrings.running ) || ( mStrings && mStrings.collecting ) || 'Working…';
			}
		} else {
			btn.removeAttribute( 'data-loading' );
			btn.disabled = false;
			if ( labelEl ) {
				labelEl.textContent = ( diagStrings && diagStrings.runDiagnostics ) || ( mStrings && mStrings.collect ) || 'Run';
			}
		}
	}

	/**
	 * POST to a REST endpoint with nonce.
	 */
	function wpdFetch( url, method, body, nonceVal ) {
		var headers = {
			'X-WP-Nonce': wpRestNonce,
			'Accept': 'application/json',
		};
		var opts = {
			method: method,
			credentials: 'same-origin',
			headers: headers,
		};
		if ( body ) {
			var fd = new FormData();
			fd.append( 'wpd_nonce', nonceVal );
			Object.keys( body ).forEach( function ( k ) {
				fd.append( k, body[ k ] );
			} );
			opts.body = fd;
		}
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
		setButtonLoading( collectBtn, collectLabel, true );
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
				setButtonLoading( collectBtn, collectLabel, false );
				showNotice( collectNotice, collectStrings.success || 'Collection complete.', 'success' );
				setTimeout( function () { window.location.reload(); }, 800 );
			} )
			.catch( function ( error ) {
				setButtonLoading( collectBtn, collectLabel, false );
				showNotice( collectNotice, error.message || collectStrings.error || 'Collection failed.', 'error' );
			} );
	}

	if ( collectBtn ) {
		collectBtn.addEventListener( 'click', triggerDashboardCollection );
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
		setButtonLoading( btn, lbl, true );
		showNotice( diagNotice, diagStrings.running || 'Running diagnostics…', 'info' );

		wpdFetch( diagRestUrl + 'run', 'POST', null, diagNonce )
			.then( function ( response ) {
				if ( ! response.ok ) {
					return response.json().then( function ( err ) {
						throw new Error( ( err && err.message ) || 'Diagnostics failed.' );
					} );
				}
				return response.json();
			} )
			.then( function ( data ) {
				setButtonLoading( btn, lbl, false );
				showNotice( diagNotice, diagStrings.complete || 'Diagnostics complete.', 'success' );
				setTimeout( function () { window.location.reload(); }, 800 );
			} )
			.catch( function ( err ) {
				setButtonLoading( btn, lbl, false );
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
			return;
		}

		if ( ! confirm( cls.contains( 'wpd-button--ignore' ) ? ( diagStrings.confirmIgnore || 'Ignore this finding?' ) : ( cls.contains( 'wpd-button--ack' ) ? diagStrings.confirmAck || 'Acknowledge this finding?' : '' ) ) ) {
			return;
		}

		wpdFetch( diagRestUrl + path, 'POST', { wpd_nonce: nonce }, nonce )
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
	var detailModalCloseBtn = document.getElementById( 'wpd-modal-close-btn' );
	var detailContent       = document.getElementById( 'wpd-detail-modal-content' );
	var detailTitle         = document.getElementById( 'wpd-detail-modal-title' );

	function openFindingDetails( id ) {
		if ( ! diagRestUrl || ! detailModal || ! detailContent ) {
			return;
		}
		detailContent.innerHTML = '<div class="wpd-loading"><div class="wpd-loading-spinner"></div><div class="wpd-loading-text">' + ( diagStrings.running || 'Loading…' ) + '</div></div>';
		detailModalBackdrop.hidden = false;
		// Small delay to allow backdrop transition
		requestAnimationFrame( function () {
			detailModalBackdrop.classList.add( 'wpd-modal-backdrop--visible' );
		} );

		wpdFetch( diagRestUrl + 'findings/' + id, 'GET', null, diagNonce )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Failed to load finding details.' );
				}
				return response.json();
			} )
			.then( function ( data ) {
				renderFindingDetails( data );
			} )
			.catch( function () {
				detailContent.innerHTML = '<div class="wpd-notice wpd-notice--error"><p>' + ( diagStrings.error || 'Failed to load details.' ) + '</p></div>';
			} );
	}

	function renderFindingDetails( f ) {
		var ev = f.evidence || {};
		var rem = f.remediation || {};
		var steps = Array.isArray( rem.steps ) ? rem.steps : [];

		var html = '';
		html += '<div class="wpd-detail-grid">';

		html += '<div class="wpd-detail-field">';
		html += '<span class="wpd-detail-field-label">' + escapeHtml( __( 'Category', 'wp-performance-doctor' ) ) + '</span>';
		html += '<span class="wpd-detail-field-value">' + escapeHtml( f.category || '' ) + '</span>';
		html += '</div>';

		html += '<div class="wpd-detail-field">';
		html += '<span class="wpd-detail-field-label">' + escapeHtml( __( 'Severity', 'wp-performance-doctor' ) ) + '</span>';
		html += '<span class="wpd-detail-field-value"><span class="wpd-severity-badge wpd-severity--' + escapeHtml( f.severity || '' ) + '">' + escapeHtml( ucfirstJs( f.severity || '' ) ) + '</span></span>';
		html += '</div>';

		html += '<div class="wpd-detail-field">';
		html += '<span class="wpd-detail-field-label">' + escapeHtml( __( 'Confidence', 'wp-performance-doctor' ) ) + '</span>';
		html += '<span class="wpd-detail-field-value"><span class="wpd-severity-badge wpd-severity--' + escapeHtml( f.confidence || '' ) + '">' + escapeHtml( ucfirstJs( f.confidence || '' ) ) + '</span></span>';
		html += '</div>';

		html += '<div class="wpd-detail-field">';
		html += '<span class="wpd-detail-field-label">' + escapeHtml( __( 'Status', 'wp-performance-doctor' ) ) + '</span>';
		html += '<span class="wpd-detail-field-value"><span class="wpd-status-badge wpd-status-badge--small wpd-status--' + escapeHtml( f.status || '' ) + '">' + escapeHtml( ucfirstJs( f.status || '' ) ) + '</span></span>';
		html += '</div>';

		html += '<div class="wpd-detail-field">';
		html += '<span class="wpd-detail-field-label">' + escapeHtml( __( 'First Detected', 'wp-performance-doctor' ) ) + '</span>';
		html += '<span class="wpd-detail-field-value">' + escapeHtml( f.first_detected || '' ) + '</span>';
		html += '</div>';

		html += '<div class="wpd-detail-field">';
		html += '<span class="wpd-detail-field-label">' + escapeHtml( __( 'Last Detected', 'wp-performance-doctor' ) ) + '</span>';
		html += '<span class="wpd-detail-field-value">' + escapeHtml( f.last_detected || '' ) + '</span>';
		html += '</div>';

		html += '</div>';

		html += '<div class="wpd-detail-section" style="margin-top: var(--wpd-space-6);">';
		html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Description', 'wp-performance-doctor' ) ) + '</h3>';
		html += '<p>' + escapeHtml( f.description || '' ) + '</p>';
		html += '</div>';

		html += '<div class="wpd-detail-section">';
		html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Impact', 'wp-performance-doctor' ) ) + '</h3>';
		html += '<p>' + escapeHtml( f.impact || '' ) + '</p>';
		html += '</div>';

		if ( Object.keys( ev ).length ) {
			html += '<div class="wpd-detail-section">';
			html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Evidence', 'wp-performance-doctor' ) ) + '</h3>';
			html += '<div class="wpd-badge-list">';
			Object.keys( ev ).forEach( function ( key ) {
				html += '<span class="wpd-badge wpd-badge--outline" style="margin-bottom: var(--wpd-space-2); display: inline-block;"><strong>' + escapeHtml( key ) + ':</strong> ' + escapeHtml( String( ev[ key ] ) ) + '</span>';
			} );
			html += '</div>';
			html += '</div>';
		}

		html += '<div class="wpd-detail-section">';
		html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Recommendation', 'wp-performance-doctor' ) ) + '</h3>';
		html += '<p>' + escapeHtml( f.recommendation || '' ) + '</p>';
		html += '</div>';

		if ( steps.length ) {
			html += '<div class="wpd-detail-section">';
			html += '<h3 class="wpd-detail-section-title">' + escapeHtml( __( 'Remediation Steps', 'wp-performance-doctor' ) ) + '</h3>';
			html += '<ul class="wpd-small-text" style="margin: 0; padding-left: var(--wpd-space-4); list-style: disc;">';
			steps.forEach( function ( step ) {
				html += '<li style="margin-bottom: var(--wpd-space-1);">' + escapeHtml( String( step ) ) + '</li>';
			} );
			html += '</ul>';
			html += '</div>';
		}

		html += '<div class="wpd-detail-section">';
		html += '<div class="wpd-detail-field">';
		html += '<span class="wpd-detail-field-label">' + escapeHtml( __( 'Risk', 'wp-performance-doctor' ) ) + '</span>';
		html += '<span class="wpd-detail-field-value">' + escapeHtml( rem.risk || '' ) + '</span>';
		html += '</div>';
		html += '<div class="wpd-detail-field">';
		html += '<span class="wpd-detail-field-label">' + escapeHtml( __( 'Difficulty', 'wp-performance-doctor' ) ) + '</span>';
		html += '<span class="wpd-detail-field-value">' + escapeHtml( rem.difficulty || '' ) + '</span>';
		html += '</div>';
		html += '<div class="wpd-detail-field">';
		html += '<span class="wpd-detail-field-label">' + escapeHtml( __( 'Manual Action', 'wp-performance-doctor' ) ) + '</span>';
		html += '<span class="wpd-detail-field-value">' + ( rem.requires_manual_action ? 'Yes' : 'No' ) + '</span>';
		html += '</div>';
		html += '</div>';

		detailContent.innerHTML = html;
		detailTitle.textContent = escapeHtml( f.title || '' );
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
	if ( detailModalCloseBtn ) {
		detailModalCloseBtn.addEventListener( 'click', closeModal );
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

} )();
