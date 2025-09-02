document.addEventListener('DOMContentLoaded', function(){
	// Helper to parse input for explicit multiplier, e.g. '123 X2D' or '123 X3D'
	function parseInputWithMultiplier(raw) {
		// Example accepted formats: '123 X2D', '456 X3D', '789', 'A', etc.
		const result = {
			valid: false,
			num: '',
			explicitMultiplier: null,
			explicitMultiplierInput: false,
			label: '',
		};
		if (typeof raw !== 'string') return result;
		const trimmed = raw.trim();
		// Match number and optional multiplier (e.g., '123 X2D')
		const match = trimmed.match(/^(\d+)(?:\s*[xX](\d)[dD])?$/);
		if (match) {
			result.num = match[1];
			if (match[2]) {
				result.explicitMultiplier = parseInt(match[2], 10);
				result.explicitMultiplierInput = true;
			}
			result.valid = true;
			return result;
		}
		// If only letters, treat as label
		if (/^[A-Za-z]+$/.test(trimmed)) {
			result.label = trimmed;
			result.valid = true;
			return result;
		}
		return result;
	}
	// Add helper to parse input with separator format (e.g. 'ABCD:123:100')
	function parseInputWithSeparator(raw) {
		const result = {
			valid: false,
			letters: '',
			digits: '',
			bet: 0
		};
		if (typeof raw !== 'string') return result;
		const trimmed = raw.trim();
		// Accept formats like 'ABCD:123:100', 'A:12:200', etc.
		const parts = trimmed.split(':').map(s => s.trim());
		if (parts.length === 3) {
			// Format: letters:digits:bet
			result.letters = parts[0].replace(/[^A-Za-z]/g, '').toUpperCase();
			result.digits = parts[1].replace(/\D/g, '');
			result.bet = parseInt(parts[2], 10) || 0;
			if (result.digits && result.bet > 0) {
				result.valid = true;
			}
		} else if (parts.length === 2) {
			// Format: letters:digits (use default bet)
			result.letters = parts[0].replace(/[^A-Za-z]/g, '').toUpperCase();
			result.digits = parts[1].replace(/\D/g, '');
			result.bet = 100; // default bet
			if (result.digits) {
				result.valid = true;
			}
		}
		return result;
	}

	// Helper to update input mode UI (toggle classes or states)
	function updateInputModeUI() {
		// Example: highlight chosen and bet fields if editable
		if (chosen && !chosen.readOnly) {
			chosen.classList.add('active-input');
		} else if (chosen) {
			chosen.classList.remove('active-input');
		}
		if (bet && !bet.readOnly) {
			bet.classList.add('active-input');
		} else if (bet) {
			bet.classList.remove('active-input');
		}
	}
	// Helper to set bet input editable or readonly
	function setBetEditable(editable) {
		if (!bet) return;
		bet.readOnly = !editable;
		if (editable) {
			bet.classList.add('active-input');
		} else {
			bet.classList.remove('active-input');
		}
	}
	// Load Battambang font from Google Fonts
	function loadBattambangFont() {
		// Add preconnect links
		const preconnect1 = document.createElement('link');
		preconnect1.rel = 'preconnect';
		preconnect1.href = 'https://fonts.googleapis.com';
		document.head.appendChild(preconnect1);
		
		const preconnect2 = document.createElement('link');
		preconnect2.rel = 'preconnect';
		preconnect2.href = 'https://fonts.gstatic.com';
		preconnect2.crossOrigin = 'anonymous';
		document.head.appendChild(preconnect2);
		
		// Add font stylesheet
		const fontLink = document.createElement('link');
		fontLink.rel = 'stylesheet';
		fontLink.href = 'https://fonts.googleapis.com/css2?family=Battambang:wght@100;300;400;700;900&display=swap';
		document.head.appendChild(fontLink);
		
		// Load custom CSS file with font styles
		const cssLink = document.createElement('link');
		cssLink.rel = 'stylesheet';
		cssLink.href = 'assets/styles.css';
		document.head.appendChild(cssLink);
	}
	
	// Load the font
	loadBattambangFont();
	
	const chosen = document.getElementById('chosen');
	const bet = document.getElementById('bet');
	const keypad = document.getElementById('keypad');
	const cartInput = document.getElementById('cartData');
	const addBtn = document.getElementById('addTicket');
	const multiplierCheckbox = document.getElementById('useMultiplier');

	// new: client-side cart
	let cart = []; // each item: {num: "0123", bet: 100, label: "A", opts: []}

	// preview area
	const previewEl = document.querySelector('#previewBox') || document.querySelector('.placeholder');

	// modal elements
	const ticketModal = document.getElementById('ticketModal');
	const ticketContent = document.getElementById('ticketContent');
	const closeTicket = document.getElementById('closeTicket');

	function escapeHtml(s){
		return String(s).replace(/[&<>"']/g, function(m){
			return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
		});
	}
	// show 'None' when input is empty or zero-equivalent
	function fmtCurrency(n){
		if (n === undefined || n === null) return 'None';
		// treat empty string as none
		if (typeof n === 'string' && n.trim() === '') return 'None';
		n = Number(n) || 0;
		if (n === 0) return 'None';
		return n.toLocaleString() + '៛';
	}

	// Ensure openTicketModal is a function declaration available when Recent list handlers are attached
	function openTicketModal(idx){
		const plays = window.PLAY_HISTORY || [];
		const p = plays[idx];
		if (!p) return;
		
		// Calculate total with letter multipliers
		let calculatedTotal = 0;
		p.items.forEach(item => {
			const betAmount = Number(item.bet || 0);
			const label = (item.label || '').trim().toUpperCase();
			const multiplier = letterMultiplierFromLabel(label);
			
			if (multiplier > 0) {
				calculatedTotal += betAmount * multiplier;
			} else {
				calculatedTotal += betAmount;
			}
		});
		
		// Generate a ticket ID for saving the file
		const ticketCode = 'P' + (Math.floor(Math.random()*900000) + 100000);
		const nowLabel = escapeHtml(p.time || '');
		
		// Create a wrapper div that will be used for the content (for capturing to image)
		let html = `<div id="ticketCapture">`;
		html += '<div style="text-align:center;padding-bottom:8px;border-bottom:1px solid #ddd;">';
		html += '<div style="font-weight:900;font-size:22px;">8888 Lottery</div>';
		html += '<div style="margin-top:6px;font-weight:700;">ទទួលឆ្នោតគ្រប់ប្រភេទ</div>';
		html += '</div>';

		html += '<div style="padding:12px 6px;">';
		html += '<div style="font-weight:700;margin-bottom:8px;">បកសួរ: MC</div>';
		html += '<div style="font-size:13px;color:#333;margin-bottom:10px;">' + escapeHtml(window.CURRENT_USER || '') + ' / #' + ticketCode + '<br>' + nowLabel + '</div>';
		html += '<hr/>';
		
		// Group items by their letter labels
		const groupedByLabel = {};
		p.items.forEach(it => {
			const label = (it.label || '').trim().toUpperCase();
			if (!groupedByLabel[label]) {
				groupedByLabel[label] = {
					label: label,
					items: []
				};
			}
			groupedByLabel[label].items.push(it);
		});
		
		// Generate HTML for each group
		Object.values(groupedByLabel).forEach(group => {
			// Show the label as a header if it exists
			if (group.label) {
				html += '<div style="font-weight:700;font-size:18px;margin-top:12px;">' + escapeHtml(group.label) + '</div>';
			}
			
			// Show each item in the group
			group.items.forEach(it => {
				const displayNum = (it.num_raw && String(it.num_raw).length > 0) ? String(it.num_raw) : String(it.num || '');
				html += '<div style="font-size:20px;font-weight:700;">' + escapeHtml(displayNum) + (displayNum ? ' : ' + fmtCurrency(it.bet) : '') + '</div>';
			});
		});
		
		html += '<hr/>';
		// Use calculated total that includes letter multipliers
		html += '<div style="display:flex;justify-content:space-between;font-weight:800;"><div>សរុប :</div><div>' + fmtCurrency(calculatedTotal || 0) + '</div></div>';
		html += '<div style="display:flex;justify-content:space-between;color:#999;margin-top:6px;"><div> :</div><div>0$</div></div>';
		html += '<div style="text-align:center;margin-top:16px;font-size:12px;color:#666;">' + nowLabel + ' លេខបញ្ជា(' + (p.items.length) + ')</div>';
		html += '</div>'; // End of ticket capture div
		
		// Set the ticket content HTML without action buttons
		ticketContent.innerHTML = html;
		
		// Create action buttons container
		const actionButtonsContainer = document.createElement('div');
		actionButtonsContainer.className = 'ticket-action-buttons';
		actionButtonsContainer.style.cssText = 'margin-top:16px;display:grid;justify-content:space-between;padding:0 16px 16px 16px;';
		
		// Create Save button
		const saveButton = document.createElement('button');
		saveButton.id = 'saveTicketBtn';
		saveButton.className = 'btn';
		saveButton.style.cssText = 'background:#4caf50;color:white;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;';
		saveButton.innerHTML = '<span style="margin-right:4px;">💾</span> Save';
		
		// Create Copy button
		const copyButton = document.createElement('button');
		copyButton.id = 'copyTicketBtn';
		copyButton.className = 'btn';
		copyButton.style.cssText = 'background:#9c27b0;color:white;border:none;margin-top:15px;padding:8px 16px;border-radius:4px;cursor:pointer;';
		copyButton.innerHTML = '<span style="margin-right:4px;">📋</span> Copy';
		
		// Create Print button
		const printButton = document.createElement('button');
		printButton.id = 'printTicketBtn';
		printButton.className = 'btn';
		printButton.style.cssText = 'background:#2196f3;color:white;border:none; margin-top:15px; padding:8px 16px;border-radius:4px;cursor:pointer;';
		printButton.innerHTML = '<span style="margin-right:4px;">🖨️</span> Print';
		
		// Add buttons to container
		actionButtonsContainer.appendChild(saveButton);
		actionButtonsContainer.appendChild(copyButton);
		actionButtonsContainer.appendChild(printButton);
		
		// Add container after ticket content
		ticketContent.parentNode.insertBefore(actionButtonsContainer, ticketContent.nextSibling);
		
		// Display the modal
		ticketModal.style.display = 'flex';
		
		// Add event listeners for the buttons
		saveButton.addEventListener('click', function() {
			saveTicketAsImage(ticketCode);
		});
		
		copyButton.addEventListener('click', function() {
			copyTicketToClipboard();
		});
		
		printButton.addEventListener('click', function() {
			printTicket();
		});
	}
	
	// Function to save the ticket as an image
	function saveTicketAsImage(ticketCode) {
		const element = document.getElementById('ticketCapture');
		
		// Use html2canvas to convert the element to a canvas
		html2canvas(element, {
			backgroundColor: '#ffffff',
			scale: 2, // Higher scale for better quality
			logging: false,
			useCORS: true
		}).then(canvas => {
			// Convert canvas to a data URL
			const imageData = canvas.toDataURL('image/png');
			
			// Create a temporary link and trigger the download
			const link = document.createElement('a');
			link.href = imageData;
			link.download = `Ticket-${ticketCode}.png`;
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
		}).catch(error => {
			console.error('Error saving ticket as image:', error);
			alert('Could not save the ticket as an image. Please try again.');
		});
	}
	
	// Function to copy the ticket as an image to clipboard
	function copyTicketToClipboard() {
		const element = document.getElementById('ticketCapture');
		
		// Use html2canvas to convert the element to a canvas
		html2canvas(element, {
			backgroundColor: '#ffffff',
			scale: 2, // Higher scale for better quality
			logging: false,
			useCORS: true
		}).then(canvas => {
			// Convert canvas to a blob
			canvas.toBlob(function(blob) {
				try {
					// Create a ClipboardItem and write to clipboard
					const item = new ClipboardItem({ 'image/png': blob });
					navigator.clipboard.write([item]).then(() => {
						alert('Ticket copied to clipboard');
					}).catch(err => {
						console.error('Clipboard write failed:', err);
						fallbackCopyToClipboard(canvas);
					});
				} catch (e) {
					console.error('Clipboard API error:', e);
					fallbackCopyToClipboard(canvas);
				}
			});
		}).catch(error => {
			console.error('Error copying ticket to clipboard:', error);
			alert('Could not copy the ticket. Please try again.');
		});
	}
	
	// Fallback copy method for browsers with limited clipboard support
	function fallbackCopyToClipboard(canvas) {
		try {
			// Create a temporary link for downloading
			const link = document.createElement('a');
			link.href = canvas.toDataURL('image/png');
			link.download = 'ticket.png';
			
			// Add to DOM, click, and remove
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			
			alert('Ticket saved as image instead (clipboard access denied)');
		} catch (e) {
			console.error('Fallback copy failed:', e);
			alert('Could not copy or save the ticket. Please try another browser.');
		}
	}
	
	// Function to print the ticket
	function printTicket() {
		// Create a new window with just the ticket content
		const element = document.getElementById('ticketCapture');
		const printWindow = window.open('', '_blank');
		
		if (!printWindow) {
			alert('Please allow pop-ups to print the ticket.');
			return;
		}
		
		// Add content to the new window
		printWindow.document.write(`
			<!DOCTYPE html>
			<html>
			<head>
				<title>Print Ticket</title>
				<style>
					body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
					@media print {
						body { width: 80mm; margin: 0 auto; }
						.print-btn { display: none; }
					}
				</style>
			</head>
			<body>
				${element.outerHTML}
				<div class="print-btn" style="margin-top: 20px; text-align: center;">
					<button onclick="window.print();return false;" style="padding: 8px 16px; background: #2196f3; color: white; border: none; border-radius: 4px; cursor: pointer;">
						Print Ticket
					</button>
				</div>
			</body>
			</html>
		`);
		
		printWindow.document.close();
	}

	// Render the Recent plays list (first box)
	function renderRecentList(){
		const recentEl = document.getElementById('recentList');
		if (!recentEl) return;
		const plays = window.PLAY_HISTORY || [];
		if (plays.length === 0) {
			recentEl.innerHTML = '<div style="text-align:center;color:#666;padding:10px;">No recent plays</div>';
			return;
		}
		
		// Process plays to apply letter multipliers to totals
		const processedPlays = plays.map(play => {
			// Create a copy to avoid modifying the original
			const processedPlay = {...play};
			
			// Recalculate total bet with letter multipliers
			let calculatedTotal = 0;
			(play.items || []).forEach(item => {
				const betAmount = Number(item.bet || 0);
				const label = (item.label || '').trim().toUpperCase();
				const multiplier = letterMultiplierFromLabel(label);
				
				// If there's a letter multiplier, multiply the bet
				if (multiplier > 0) {
					calculatedTotal += betAmount * multiplier;
				} else {
					calculatedTotal += betAmount;
				}
			});
			
			// Update the total bet value
			processedPlay.calculated_total_bet = calculatedTotal;
			return processedPlay;
		});
		
		let rows = processedPlays.map((p, idx) => {
			const ticketId = String(10000000 + idx);
			const timeLabel = escapeHtml(p.time || '');
			// Use the calculated total (with letter multipliers) instead of the raw total
			const tb = fmtCurrency(p.calculated_total_bet || 0);
			const tw = fmtCurrency(p.total_win || 0);
			
			// add Show, Delete and Edit buttons per play
			return '<div class="recent-row" data-idx="'+idx+'" style="display:flex;justify-content:space-between;align-items:center;padding:8px;border-bottom:1px solid #eee;cursor:pointer;">' +
				'<div style="flex:1"><div style="font-weight:700;">#'+ticketId+'</div><div style="font-size:12px;color:#666;">'+timeLabel+'</div></div>' +
				'<div style="text-align:right;"><div style="font-weight:700;color:#222;">'+tb+'</div><div style="font-size:12px;color:#c00;">'+tw+'</div>' +
				'<div style="margin-top:6px;">' +
					'<button class="show-btn" data-idx="'+idx+'" style="padding:6px 8px;border-radius:8px;border:none;background:#2196f3;color:#fff;cursor:pointer;margin-right:6px;">Show</button>' +
					'<button class="delete-btn" data-idx="'+idx+'" style="padding:6px 8px;border-radius:8px;border:none;background:#e74c3c;color:#fff;cursor:pointer;margin-right:6px;">Delete</button>' +
					'<button class="edit-btn" data-idx="'+idx+'" style="padding:6px 8px;border-radius:8px;border:none;background:#4caf50;color:#fff;cursor:pointer;">Edit</button>' +
				'</div></div>' +
				'</div>';
		}).join('');
		
		recentEl.innerHTML = '<div style="font-weight:700;padding:6px 8px;border-bottom:2px solid #ddd;">Recent Plays</div><div style="max-height:220px;overflow:auto;">'+rows+'</div>';
		// attach click handlers for opening ticket modal (row click)
		recentEl.querySelectorAll('.recent-row').forEach(el=>{
			el.addEventListener('click', function(e){
				if (e.target && e.target.classList) {
					if (e.target.classList.contains('delete-btn') || e.target.classList.contains('edit-btn') || e.target.classList.contains('show-btn')) return;
				}
				const idx = Number(this.dataset.idx);
				openTicketModal(idx);
			});
		});
		// attach show handlers
		recentEl.querySelectorAll('.show-btn').forEach(btn=>{
			btn.addEventListener('click', function(e){
				e.stopPropagation();
				const idx = Number(this.dataset.idx);
				openTicketModal(idx);
			});
		});
		// attach delete handlers (unchanged)
		recentEl.querySelectorAll('.delete-btn').forEach(btn=>{
			btn.addEventListener('click', function(e){
				e.stopPropagation();
				const idx = Number(this.dataset.idx);
				const play = (window.PLAY_HISTORY || [])[idx];
				if (!play) return;
				if (!confirm('Delete this receipt and DB records? This will revert balance.')) return;
				const form = new URLSearchParams();
				form.append('play_time', play.play_time_raw || '');
				form.append('draw', play.draw || '');
				fetch('delete_play.php', {
					method: 'POST',
					headers: {'Content-Type': 'application/x-www-form-urlencoded'},
					body: form.toString()
				}).then(r=>r.json()).then(json=>{
					if (json && json.ok) {
						window.PLAY_HISTORY.splice(idx,1);
						renderRecentList();
						const ub = document.querySelector('.user-balance');
						if (ub) ub.textContent = 'Balance: ' + (Number(json.new_balance).toLocaleString()) + '៛';
						alert('Deleted play. New balance: ' + (Number(json.new_balance).toLocaleString()) + '៛');
					} else {
						alert('Delete failed: ' + (json && json.error ? json.error : 'Unknown error'));
					}
				}).catch(()=>alert('Delete request failed'));
			});
		});
		// attach edit handlers (unchanged)
		recentEl.querySelectorAll('.edit-btn').forEach(btn=>{
			btn.addEventListener('click', function(e){
				e.stopPropagation();
				const idx = Number(this.dataset.idx);
				openEditModal(idx);
			});
		});
	}

	// open edit modal for a play (build form with inputs per item)
	function openEditModal(idx){
		const plays = window.PLAY_HISTORY || [];
		const p = plays[idx];
		if (!p) return;
		let html = '';
		html += '<div style="font-weight:900;font-size:18px;margin-bottom:8px;">Edit Receipt</div>';
		html += '<form id="editPlayForm">';
		html += '<input type="hidden" name="play_time" value="'+escapeHtml(p.play_time_raw || '')+'">';
		html += '<input type="hidden" name="draw" value="'+escapeHtml(p.draw || '')+'">';
		// NEW: total bet editable at top of form
		html += '<label style="display:block;margin-top:7px;font-weight:700">Total Bet (local only)</label>';
		html += '<input name="total_bet" value="'+escapeHtml(String(p.total_bet || ''))+'" class="input" />';
		// existing item fields follow
		p.items.forEach((it, i) => {
			html += '<div style="margin-bottom:10px;padding:8px;border:1px solid #eee;border-radius:8px;">';
			html += '<div style="font-size:12px;color:#666;">Item #'+(i+1)+'</div>';
			html += '<input type="hidden" name="items['+i+'][db_id]" value="'+escapeHtml(it.db_id || '')+'">';
			html += '<label style="display:block;margin-top:6px;font-weight:700">Label</label>';
			html += '<input name="items['+i+'][label]" value="'+escapeHtml(it.label||'')+'" class="input" />';
			html += '<label style="display:block;margin-top:6px;font-weight:700">Number (raw)</label>';
			html += '<input name="items['+i+'][num_raw]" value="'+escapeHtml(it.num_raw||'')+'" class="input" />';
			html += '<label style="display:block;margin-top:6px;font-weight:700">Bet</label>';
			html += '<input name="items['+i+'][bet]" value="'+escapeHtml(String(it.bet||''))+'" class="input" />';
			html += '</div>';
		});
		html += '<div style="display:flex;gap:8px;"><button type="button" id="saveEdit" class="btn btn-teal">Save</button><button type="button" id="cancelEdit" class="btn btn-gray">Cancel</button></div>';
		html += '</form>';

		ticketContent.innerHTML = html;
		ticketModal.style.display = 'flex';

		// cancel
		document.getElementById('cancelEdit').addEventListener('click', function(){ ticketModal.style.display = 'none'; });

		// save handler - send to server for persistence
		document.getElementById('saveEdit').addEventListener('click', function(){
			const formEl = document.getElementById('editPlayForm');
			const fd = new FormData(formEl);

			// Send the form data to edit_play.php
			fetch('edit_play.php', {
				method: 'POST',
				body: fd
			})
			.then(response => response.json())
			.then(data => {
				if (data.ok) {
					// Update local play history with server response
					const updatedPlay = data.play;
					if (updatedPlay && window.PLAY_HISTORY && window.PLAY_HISTORY[idx]) {
						window.PLAY_HISTORY[idx] = updatedPlay;
					}
					
					// Update balance if provided
					if (data.new_balance !== undefined) {
						// Update balance display with proper formatting like in PHP
						const balanceEl = document.querySelector('div[style*="font-size:12px;color:rgba(255,255,255,0.9)"]');
						if (balanceEl) {
							balanceEl.textContent = data.new_balance.toLocaleString() + '៛';
						}
						// Update session balance
						if (typeof window !== 'undefined' && window.sessionStorage) {
							window.sessionStorage.setItem('balance', data.new_balance);
						}
					}

					// Refresh UI and close modal
					renderRecentList();
					ticketModal.style.display = 'none';
					alert('Successfully updated receipt and balance.');
				} else {
					alert('Error updating receipt: ' + (data.error || 'Unknown error'));
				}
			})
			.catch(error => {
				console.error('Error:', error);
				alert('Network error while updating receipt. Please try again.');
			});
		});
	}

	if (closeTicket) {
		closeTicket.addEventListener('click', function(){
			ticketModal.style.display = 'none';
			// Remove action buttons if they exist
			const actionButtons = document.querySelector('.ticket-action-buttons');
			if (actionButtons) {
				actionButtons.remove();
			}
		});
	}
	// close on backdrop click
	if (ticketModal) {
		ticketModal.addEventListener('click', function(e){
			if (e.target === ticketModal) {
				ticketModal.style.display = 'none';
				// Remove action buttons if they exist
				const actionButtons = document.querySelector('.ticket-action-buttons');
				if (actionButtons) {
					actionButtons.remove();
				}
			}
		});
	}

	// --- existing cart/preview code follow (unchanged) ---

	// helper: compute letter multiplier (count of unique letters among A,B,C,D)
	function letterMultiplierFromLabel(lbl){
		if (!lbl) return 0;
		const s = String(lbl).toUpperCase().replace(/[^ABCD]/g,'');
		if (!s) return 0;
		const uniq = Array.from(new Set(s.split(''))).filter(Boolean);
		return uniq.length; // 0..4
	}

	function renderCartPreview(){
		const previewElLocal = previewEl;
		if (!previewElLocal) return;

		// Empty cart state
		if (!Array.isArray(cart) || cart.length === 0) {
			previewElLocal.innerHTML = `
				<div class="cart-empty">
					<div class="cart-empty-inner">
						<div class="magnify">🔍</div>
						<p class="muted">Selected ticket preview</p>
					</div>
				</div>`;
			return;
		}

		// Group by label while preserving original cart index for removals
		const grouped = {};
		cart.forEach((it, idx) => {
			const label = ((it.label || '').trim().toUpperCase()) || '';
			if (!grouped[label]) grouped[label] = { label, items: [], totalBet: 0, letterMulti: letterMultiplierFromLabel(label) };
			grouped[label].items.push(Object.assign({}, it, { __idx: idx }));
			grouped[label].totalBet += Number(it.bet || 0);
		});

		// Compute per-item xMultiplier and total winnings
		let totalWinnings = 0;
		Object.values(grouped).forEach(g => {
			const letterMult = g.letterMulti > 0 ? g.letterMulti : 1;
			g.items.forEach(it => {
				const numStr = it.num || it.num_padded || it.num_raw || '';
				// Use explicit multiplier regardless of checkbox state if it exists
				const xMulti = getDefaultMultiplier(numStr, it);
				it.xMultiplier = xMulti;
				it.displayNum = (it.num_raw && String(it.num_raw).length) ? String(it.num_raw) : (it.num || '');
				it.win = Number(it.bet || 0) * letterMult * xMulti;
				totalWinnings += it.win;
			});
		});

		const nf = new Intl.NumberFormat();

		// Build HTML
		let html = `<div class="cart-preview">`;
		html += `
			<div class="cart-header">
				<button type="button" class="refresh-preview" title="Refresh">⟳</button>
				<button type="button" class="delete-cart" title="Delete ticket">🗑</button>
			</div>
			<div class="cart-content">`;

		Object.values(grouped).forEach(g => {
			html += `<div class="cart-group">`;
			if (g.label) html += `<div class="cart-label">${escapeHtml(g.label)}</div>`;
			html += `<div class="cart-items">`;
			g.items.forEach(it => {
				const displayNum = escapeHtml(it.displayNum || '');
				const betLabel = it.bet ? nf.format(Number(it.bet)) + '៛' : '0៛';
				let multBadge = '';
				const digits = String(displayNum).replace(/\D/g,'').length;
				
				// IMPORTANT: Use the frozen display state from when the item was added
				// This is independent of the current checkbox state
				if (it.explicitMultiplierInput && it.explicitMultiplier) {
					// Explicit multipliers ALWAYS show regardless of checkbox
					multBadge = `<span class="multiplier-badge multiplier-explicit">X${it.explicitMultiplier}D</span>`;
				} 
				// For auto multipliers, only show if they should be displayed based on when added
				else if ((digits === 2 || digits === 3) && it.shouldDisplayMultiplier === true) {
					multBadge = `<span class="multiplier-badge">X${it.xMultiplier}D</span>`;
				}
				
				const itemClass = it.explicitMultiplierInput ? 'cart-item has-explicit-multiplier' : 'cart-item';
				
				html += `
					<div class="${itemClass}">
						<div class="cart-value">
							<span class="preview-num">${displayNum || '&nbsp;'}</span>
							${multBadge}
							<span class="sep"> : </span>
							<span class="preview-bet">${betLabel}</span>
						</div>
						<button type="button" class="remove-item" data-idx="${it.__idx}" title="Remove">×</button>
					</div>`;
			});
			if (g.totalBet) html += `<div class="cart-group-total">${nf.format(g.totalBet)}៛</div>`;
			html += `</div></div>`;
		});

		html += `</div>`;
		html += `
			<div class="cart-footer">
				<div class="total-value">${nf.format(totalWinnings)}៛</div>
				<div class="dollar-value">0$</div>
			</div>`;
		html += `</div>`;

		previewElLocal.innerHTML = html;

		// Attach handlers
		const refreshBtn = previewElLocal.querySelector('.refresh-preview');
		if (refreshBtn) refreshBtn.addEventListener('click', updatePreview);

		const delBtn = previewElLocal.querySelector('.delete-cart');
		if (delBtn) delBtn.addEventListener('click', function(){
			if (!confirm('Delete this ticket?')) return;
			cart = [];
			syncCartInput();
			renderCartPreview();
		});

		previewElLocal.querySelectorAll('.remove-item').forEach(btn => {
			btn.addEventListener('click', function(e){
				e.stopPropagation();
				const i = Number(this.dataset.idx);
				if (!isNaN(i)) {
					cart.splice(i,1);
					syncCartInput();
					renderCartPreview();
				}
			});
		});
	}

	function syncCartInput(){
		if (cartInput) cartInput.value = JSON.stringify(cart);
	}

	function updatePreview(){
		// Check if input contains a separator and should be parsed differently
		const rawInput = (chosen && chosen.value || '').trim();
		if (rawInput.includes(':')) {
			const parsedInput = parseInputWithSeparator(rawInput);
			if (parsedInput.valid) {
				// Use the parsed input values
				chosen.value = parsedInput.letters + parsedInput.digits;
				bet.value = parsedInput.bet.toString();
			}
		}
		
		if (cart.length > 0) {
			renderCartPreview();
			return;
		}
		
		if (!previewEl) return;
		
		const num = (chosen && chosen.value || '').trim();
		const b = (bet && bet.value || '').trim();
		
		// Empty state
		if (!num && (b === '' || b === '0' || Number(b) === 0)) {
			previewEl.innerHTML = `
				<div class="cart-empty">
					<div class="cart-empty-inner">
						<div class="magnify">🔍</div>
						<p class="muted">Selected ticket preview</p>
					</div>
				</div>`;
			return;
		}
		
		// Build a sample preview with multiple entries like in the images
		const betAmount = parseInt(b, 10) || 100;
		
		// Parse input to check if it already has structured format
		// Otherwise, create a sample structure from current input
		let entries = [];
		
		// Check if we're dealing with a structured entry like "ABCD\n12 X2D\n456 X3D"
		if (num.includes('X2D') || num.includes('X3D') || num.includes('X')) {
			// Split input by digits + X pattern
			const parts = num.split(/(\d+\s*X\d+D?)/g).filter(Boolean).map(p => p.trim());
			
			// Extract ABCD part if present
			let letters = '';
			if (parts.length > 0 && /^[ABCD]+$/i.test(parts[0])) {
				letters = parts.shift().toUpperCase();
			}
			
			// Process remaining parts as entries
			for (let i = 0; i < parts.length; i++) {
				const part = parts[i];
				const numMatch = part.match(/(\d+)/);
				const xMatch = part.match(/X(\d+)/i);
				
				if (numMatch) {
					entries.push({
						num: numMatch[1],
						xMultiplier: xMatch ? parseInt(xMatch[1], 10) : getDefaultMultiplier(numMatch[1]),
						hasD: part.includes('D'),
						bet: betAmount
					});
				}
			}
			
			// If we found structured entries, add ABCD as first entry
			if (entries.length > 0 && letters) {
				entries = [{letters: letters, isHeader: true}].concat(entries);
			} else if (entries.length === 0) {
				// Fallback to simple display if parsing failed
				entries = [
					{letters: 'ABCD', isHeader: true},
					{num: '12', xMultiplier: 2, hasD: true, bet: betAmount},
					{num: '456', xMultiplier: 3, hasD: true, bet: betAmount}
				];
			}
		} else {
			// Default sample entries if input doesn't match structured format
			const digits = num.replace(/\D/g, '');
			const letters = num.replace(/[^A-Za-z]/g, '').toUpperCase();
			
			if (digits) {
				// Use actual input with appropriate multiplier
				const xMultiplier = getDefaultMultiplier(digits);
				entries = [
					{letters: letters || 'ABCD', isHeader: true},
					{num: digits, xMultiplier: xMultiplier, hasD: true, bet: betAmount}
				];
			} else if (letters) {
				// Letters only
				entries = [
					{letters: letters, isHeader: true},
					{num: '12', xMultiplier: 2, hasD: true, bet: betAmount},
					{num: '456', xMultiplier: 3, hasD: true, bet: betAmount}
				];
			} else {
				// Default example
				entries = [
					{letters: 'ABCD', isHeader: true},
					{num: '12', xMultiplier: 2, hasD: true, bet: betAmount},
					{num: '456', xMultiplier: 3, hasD: true, bet: betAmount}
				];
			}
		}
		
		// Calculate total winnings with respect to multiplier checkbox
		let totalWinnings = 0;

		// Determine header letter multiplier (if first entry is header)
		let headerLetters = '';
		if (entries.length > 0 && entries[0].isHeader && entries[0].letters) {
			headerLetters = entries[0].letters;
		}
		const headerLetterMultiplier = headerLetters ? letterMultiplierFromLabel(headerLetters) : 0;

		// Calculate wins for each entry and sum the total
		entries.forEach(entry => {
			if (!entry.isHeader) {
				// Effective multiplier = X multiplier × letter multiplier (or 1 if no letter multiplier)
				const letterMult = headerLetterMultiplier > 0 ? headerLetterMultiplier : 1;
				// Apply digit multiplier only if checkbox is checked
                const digitMult = multiplierCheckbox && multiplierCheckbox.checked ? 
                    (entry.xMultiplier || 1) : 1;
                
				entry.effectiveMultiplier = digitMult * letterMult;
				entry.win = (Number(entry.bet) || 0) * entry.effectiveMultiplier;
				totalWinnings += entry.win;
			}
		});
		
		// Build HTML for preview matching the sample image
		let previewHTML = `
			<div class="ticket-preview" style="position:relative;">
				<div style="display:flex;justify-content:space-between;align-items:center;padding:8px;">
					<button type="button" class="clear-cart" style="background:none;border:none;cursor:pointer;">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M15 15L9 9M15 9L9 15"/>
						</svg>
					</button>
					<button type="button" class="delete-cart" style="background:none;border:none;cursor:pointer;">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#cc0000" stroke-width="2">
							<path d="M3 6h18M6 6v14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V6M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>
						</svg>
					</button>
				</div>
				<div style="padding:0 15px 15px;">`;
		
		// Add entries
		entries.forEach(entry => {
			if (entry.isHeader && entry.letters) {
				previewHTML += `<div style="margin-bottom:5px;font-size:28px;font-weight:bold;">${entry.letters}</div>`;
			} else if (entry.num) {
				// Only show multiplier if checkbox is checked
				const showMultiplier = multiplierCheckbox && multiplierCheckbox.checked;
				const xDisplay = (showMultiplier && entry.xMultiplier) ? 
					`X${entry.xMultiplier}${entry.hasD ? 'D' : ''}` : '';
				
				previewHTML += `
					<div style="display:flex;align-items:center;margin-bottom:5px;font-size:28px;font-weight:bold;">
						${entry.num} ${xDisplay ? `<span style="color:#00a0e9;margin:0 4px;">${xDisplay}</span>` : ''} : ${entry.bet}៛
					</div>`;
			}
		});
		
		// Add total
		previewHTML += `</div>
				<hr style="border:0;height:1px;background:#ddd;margin:0;">
				<div style="padding:15px;display:flex;justify-content:space-between;align-items:center;">
					<span style="color:#4caf50;font-size:28px;font-weight:bold;">${totalWinnings.toLocaleString()}៛</span>
					<span style="color:#2196f3;font-size:28px;font-weight:bold;">0$</span>
				</div>
			</div>`;
		
		previewEl.innerHTML = previewHTML;
	}

	// Helper function to get default multiplier based on digit count
	function getDefaultMultiplier(numStr, item) {
		// If the item has an explicit multiplier, always use that regardless of checkbox
		if (item && item.explicitMultiplier && item.explicitMultiplierInput) {
			return item.explicitMultiplier;
		}
		
		// Use the item's frozen multiplier state if available
		if (item && typeof item.useMultiplier !== 'undefined') {
			if (!item.useMultiplier) return 1;
			
			// For items in the cart, use their multiplier based on digit count
			const digits = String(numStr).replace(/\D/g, '');
			if (digits.length === 2) return 2;  // X2D for 2-digit numbers
			if (digits.length === 3) return 3;  // X3D for 3-digit numbers
			return 1;  
		} 
		
		// For preview/new items, use the current checkbox state
		if (multiplierCheckbox && !multiplierCheckbox.checked) {
			return 1;
		}
		
		// Auto-determine multiplier based on digit count
		const digits = String(numStr).replace(/\D/g, '');
		if (digits.length === 2) return 2;  // X2D for 2-digit numbers
		if (digits.length === 3) return 3;  // X3D for 3-digit numbers
		return 1;  // Default multiplier
	}

	// Update addBtn click handler to properly capture multiplier state
	if (addBtn) {
		addBtn.addEventListener('click', function(){
			const raw = (chosen.value || '').trim();
			const checkboxState = multiplierCheckbox ? multiplierCheckbox.checked : true;
			
			// First check if input contains a multiplier badge format (e.g., "123 X2D")
			const parsedMultiplierInput = parseInputWithMultiplier(raw);
			
			if (parsedMultiplierInput.valid) {
				// We have a valid input with multiplier badge format
				const digits = parsedMultiplierInput.digits;
				const label = parsedMultiplierInput.letters;
				const b = parsedMultiplierInput.bet;
				const explicitMultiplier = parsedMultiplierInput.multiplier;
				
				// If there are digits, add to cart
				if (digits && b > 0) {
					const num_raw = String(digits);
					const num_padded = String(digits).padStart(4,'0').slice(-4);
					cart.push({
						num: num_raw, 
						num_padded: num_padded, 
						bet: b, 
						label: label, 
						opts: [],
						// Important: Always respect the multiplier badge in input
						explicitMultiplier: explicitMultiplier, 
						explicitMultiplierInput: true, // Flag to indicate this was explicitly set by user
						shouldDisplayMultiplier: true  // Explicit multipliers always show
					});
					
					// reset fields and update UI
					chosen.value = '';
					bet.value = '';
					syncCartInput();
					renderCartPreview();
					return;
				}
			}
			
			// For regular or separator input formats, capture current checkbox state
			const parsedSeparatorInput = parseInputWithSeparator(raw);
			
			if (parsedSeparatorInput.valid) {
				// We have a valid input with separator format
				const digits = parsedSeparatorInput.digits;
				const label = parsedSeparatorInput.letters;
				const b = parsedSeparatorInput.bet;
				
				// If there are digits, add to cart
				if (digits && b > 0) {
					const num_raw = String(digits);
					const num_padded = String(digits).padStart(4,'0').slice(-4);
					cart.push({
						num: num_raw, 
						num_padded: num_padded, 
						bet: b, 
						label: label, 
						useMultiplier: checkboxState,
						shouldDisplayMultiplier: checkboxState // Freeze display state at time of addition
					});
					
					// reset fields and update UI
					chosen.value = '';
					bet.value = '';
					syncCartInput();
					renderCartPreview();
					return;
				}
			}
			
			// If we reach here, use the existing logic for standard input
			const digits = raw.replace(/\D/g,'');
			const label = raw.replace(/\d/g,'');
			const b = parseInt(bet.value || '0',10) || 0;

			// If there are digits, a bet is required
			if (digits) {
				if (b <= 0) {
					alert('Enter a number (digits) and bet before adding');
					return;
				}
				// store raw number (no added zeros) and also keep a padded version if needed
				const num_raw = String(digits);
				const num_padded = String(digits).padStart(4,'0').slice(-4);
				
				// Save the CURRENT multiplier preference with the cart item
				cart.push({
					num: num_raw, 
					num_padded: num_padded, 
					bet: b, 
					label: label, 
					opts: [],
					useMultiplier: checkboxState,
					shouldDisplayMultiplier: checkboxState // Freeze display state at time of addition
				});
			} else if (label) {
				// Letters-only: allow adding without bet (bet = 0, num empty)
				cart.push({
					num: '', 
					num_padded: '', 
					bet: 0, 
					label: label, 
					opts: [],
					useMultiplier: checkboxState,
					shouldDisplayMultiplier: checkboxState // Freeze display state
				});
			} else {
				// nothing meaningful entered
				alert('Enter a number (digits) and bet before adding');
				return;
			}

			// reset fields and update UI
			chosen.value = '';
			bet.value = '';
			syncCartInput();
			renderCartPreview();
		});
	}

	// Also enhance the chosen input to handle multiplier input when the user presses Enter
	if (chosen) {
		chosen.addEventListener('keydown', function(e) {
			if (e.key === 'Enter') {
				e.preventDefault(); // Prevent form submission
				
				const rawInput = chosen.value.trim();
				
				// Check for multiplier badge format
				const parsedMultiplierInput = parseInputWithMultiplier(rawInput);
				if (parsedMultiplierInput.valid && parsedMultiplierInput.digits) {
					// Update bet field if it's empty
					if (!bet.value || bet.value === '0') {
						bet.value = '100'; // Default bet
					}
					
					// Trigger add ticket
					if (addBtn) addBtn.click();
					return;
				}
				
				// Check for separator format as fallback
				if (rawInput.includes(':')) {
					const parsedSeparatorInput = parseInputWithSeparator(rawInput);
					if (parsedSeparatorInput.valid && parsedSeparatorInput.digits && parsedSeparatorInput.bet > 0) {
						// Add to bet field for visual confirmation
						bet.value = parsedSeparatorInput.bet;
						
						// Update the chosen field to keep only letters and digits
						chosen.value = parsedSeparatorInput.letters + parsedSeparatorInput.digits;
						
						// Trigger add ticket
						if (addBtn) addBtn.click();
					}
				}
			}
		});
	}

	// Add updateFocusIndicator function near other UI helper functions
	function updateFocusIndicator() {
		// Get or create the focus indicator element
		let indicator = document.getElementById('input-focus-indicator');
		if (!indicator) {
			indicator = document.createElement('div');
			indicator.id = 'input-focus-indicator';
			document.body.appendChild(indicator);
		}
		
		// Reset all classes first
		indicator.className = '';
		
		// Find active input element
		const activeInput = document.activeElement;
		
		if (!activeInput || (activeInput !== chosen && activeInput !== bet)) {
			// No valid active input, hide indicator
			indicator.style.display = 'none';
			return;
		}
		
		// Position indicator over the active input
		const rect = activeInput.getBoundingClientRect();
		indicator.style.display = 'block';
		indicator.style.top = `${rect.top + window.scrollY}px`;
		indicator.style.left = `${rect.left + window.scrollX}px`;
		indicator.style.width = `${rect.width}px`;
		indicator.style.height = `${rect.height}px`;
		
		// Add appropriate class based on active input and multiplier state
		if (activeInput === chosen) {
			const numStr = chosen.value.replace(/\D/g, '');
			
			if (multiplierCheckbox && multiplierCheckbox.checked) {
				if (numStr.length === 2) {
					indicator.classList.add('number-active-x2d');
				} else if (numStr.length === 3) {
					indicator.classList.add('number-active-x3d');
				} else {
					indicator.classList.add('number-active');
				}
			} else {
				indicator.classList.add('number-active');
			}
		} else if (activeInput === bet) {
			indicator.classList.add('bet-active');
		}
	}
	
	// Add focus and input events to update the indicator
	if (chosen) {
		chosen.addEventListener('focus', updateFocusIndicator);
		chosen.addEventListener('input', updateFocusIndicator);
	}
	
	if (bet) {
		bet.addEventListener('focus', updateFocusIndicator);
		bet.addEventListener('input', updateFocusIndicator);
	}
	
	// Handle document clicks to hide the indicator when clicking outside inputs
	document.addEventListener('click', function(e) {
		if (e.target !== chosen && e.target !== bet) {
			const indicator = document.getElementById('input-focus-indicator');
			if (indicator) {
				indicator.style.display = 'none';
			}
		}
	});

	// Update CSS for cart styling (replacing ticket-preview)
	const style = document.createElement('style');
	style.textContent = `
		.active-input {
			border-color: #4caf50 !important;
			box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.25) !important;
		}
		
		/* Cart styling (formerly ticket-preview) */
		.cart-preview {
			max-width: 220px;
			border: 2px solid #d6e6ff;
			border-radius: 12px;
			background: #fff;
			color: #111;
			overflow: hidden;
		}
		
		.cart-empty {
			min-height: 150px;
			display: flex;
			align-items: center;
			justify-content: center;
			text-align: center;
			border: 1px dashed #ccc;
			border-radius: 12px;
			background: #f9f9f9;
			color: #888;
		}
		
		.cart-header {
			padding: 6px 8px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			border-bottom: 1px solid #eee;
		}
		
		.cart-content {
			padding: 8px 10px;
		}
		
		.cart-label {
			font-weight: 800;
			font-size: 16px;
			margin-bottom: 6px;
		}
		
		.cart-item {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 4px 0;
		}
		
		.cart-value {
			display: flex;
			align-items: center;
			gap: 6px;
			font-weight: 700;
		}
		
		.cart-group-total {
			text-align: right;
			font-size: 13px;
			color: #4caf50;
			margin-top: 6px;
		}
		
		.cart-footer {
			padding: 8px 10px;
			border-top: 1px solid #eee;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		
		/* Remaining existing styles... */
		#input-focus-indicator {
			position: absolute;
			pointer-events: none;
			border-radius: 4px;
			z-index: 100;
			transition: all 0.15s ease-out;
		}
		
		#input-focus-indicator.number-active {
		
			border: 2px solid #2196f3;
			box-shadow: 0 0 8px rgba(33, 150, 243, 0.4);
		}
		
		#input-focus-indicator.number-active-x2d {
			border: 2px solid #2196f3;
			box-shadow: 0 0 8px rgba(33, 150, 243, 0.4);
		}
		
		#input-focus-indicator.number-active-x2d::after {
			content: "X2D";
			position: absolute;
			top: -20px;
			left: 50%;
			transform: translateX(-50%);
			background: #2196f3;
			color: white;
			padding: 2px 6px;
			border-radius: 3px;
			font-size: 12px;
			font-weight: bold;
		}
		
		#input-focus-indicator.number-active-x3d {
			border: 2px solid #2196f3;
			box-shadow: 0 0 8px rgba(33, 150, 243, 0.4);
		}
		
		#input-focus-indicator.number-active-x3d::after {
			content: "X3D";
			position: absolute;
			top: -20px;
			left: 50%;
			transform: translateX(-50%);
			background: #2196f3;
			color: white;
			padding: 2px 6px;
			border-radius: 3px;
			font-size: 12px;
			font-weight: bold;
		}
		
		#input-focus-indicator.bet-active {
			border: 2px solid #4caf50;
			box-shadow: 0 0 8px rgba(76, 175, 80, 0.4);
		}
		
		.key {
			transition: transform 0.12s ease, background-color 0.12s ease;
		}
		
		.key[data-action="down"] {
			position: relative;
		}
		
		.key[data-action="down"]::after {
			content: "";
			position: absolute;
			width: 80%;
			height: 2px;
			background: #fff;
			bottom: 10px;
			left: 10%;
			box-shadow: 0 1px 2px rgba(0,0,0,0.2);
		}
		
		[data-mode="bet"] .key[data-func="bet-key"] {
			background-color: rgba(76, 175, 80, 0.2);
		}
		
		[data-mode="number"] .key[data-func="number-key"] {
			background-color: rgba(33, 150, 243, 0.2);
		}
		
		/* Refined topbar styling */
	
		
		/* Add spacing below the fixed topbar */
		main.container {
			width: 100%;
			max-width: 1080px;
			margin-left: auto;
			margin-right: auto;
		}
		
		/* For responsive behavior while maintaining minimum width */
		@media (max-width: 1080px) {
			body {
				min-width: 1080px;
				overflow-x: auto;
			}
		}
	`;
	document.head.appendChild(style);
	
	// Enhanced function to improve topbar appearance
	function enhanceTopbar() {
		const topbar = document.querySelector('.topbar');
		if (!topbar) return;
		
		// If topbar already has the enhanced structure, skip
		if (topbar.querySelector('.topbar-container')) return;
		
		// Create container for proper centering
		const container = document.createElement('div');
		container.className = 'topbar-container';
		
		// Create left section for back button and logo
		const leftSection = document.createElement('div');
		leftSection.className = 'topbar-left';
		
		// Find existing back button if any
		const existingBack = topbar.querySelector('a.back');
		if (existingBack) {
			leftSection.appendChild(existingBack);
		}
		
		// Create logo section
		const logoSection = document.createElement('div');
		logoSection.className = 'logo-section';
		
		// Find existing logo circle if any, otherwise create one
		const existingLogo = topbar.querySelector('.logo-circle');
		if (existingLogo) {
			logoSection.appendChild(existingLogo);
		} else {
			const logoCircle = document.createElement('div');
			logoCircle.className = 'logo-circle';
			logoCircle.textContent = '8888';
			logoSection.appendChild(logoCircle);
		}
		
		// Add logo text
		const logoText = document.createElement('div');
		logoText.className = 'logo-text';
		logoText.textContent = 'Lottery';
		logoSection.appendChild(logoText);
		
		// Add logo section to left section
		leftSection.appendChild(logoSection);
		
		// Create right section for user info and menu
		const rightSection = document.createElement('div');
		rightSection.className = 'topbar-right';
		
		// Find existing user info
		const existingUserInfo = topbar.querySelector('.user-info');
		if (existingUserInfo) {
			// Process user info
			const userName = existingUserInfo.querySelector('.user-name');
			const userBalance = existingUserInfo.querySelector('.user-balance');
			
			if (userBalance) {
				const balanceText = userBalance.textContent;
				
				// If there's a balance, reformat it with an icon
				if (balanceText.includes('Balance:')) {
					const balanceValue = balanceText.replace('Balance:', '').trim();

					userBalance.innerHTML = 
						`<span class="balance-icon">៛</span> ${balanceValue} / 0$
						<span class="khmer-text">ពាក់យហ្គេម</span>`;
				}
			}
			
			rightSection.appendChild(existingUserInfo);
		}
		
		// Add menu button
		const menuButton = document.createElement('button');
		menuButton.className = 'menu-button';
		menuButton.setAttribute('aria-label', 'Menu');
		
		// Add three dots
		for (let i = 0; i < 3; i++) {
			const dot = document.createElement('div');
			dot.className = 'menu-dot';
			menuButton.appendChild(dot);
		}
		
		rightSection.appendChild(menuButton);
		
		// Add sections to container
		container.appendChild(leftSection);
		container.appendChild(rightSection);
		
		// Clear topbar and add new container
		while (topbar.firstChild) {
			// Check if it's something we've already used
			if (!existingBack || !topbar.firstChild.isEqualNode(existingBack)) {

				if (!existingUserInfo || !topbar.firstChild.isEqualNode(existingUserInfo)) {
					if (!existingLogo || !topbar.firstChild.isEqualNode(existingLogo)) {
						topbar.removeChild(topbar.firstChild);
					}
				}
			}
		}
		
		// Add the container to the topbar
		topbar.appendChild(container);
	}
	
	// Call the function to enhance the topbar after DOM content is loaded
	enhanceTopbar();
	
	// --- existing code follow ---

	// initialize: ensure bet starts readonly and set initial UI state
	setBetEditable(false);
	updateInputModeUI();

	// init
	updatePreview();
	renderCartPreview();
	renderRecentList();
}); // end DOMContentLoaded
