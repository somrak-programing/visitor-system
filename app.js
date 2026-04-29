document.addEventListener('DOMContentLoaded', () => {
    // --- EDIT MODE LOGIC ---
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    let isEditMode = !!editId;

    if (isEditMode) {
        document.querySelector('header h1').innerText = 'Edit Visitor Request #' + editId;
        document.querySelector('header p').innerText = 'Update the details below.';
        document.getElementById('editRequestId').value = editId;
        
        fetch(`api_my_requests.php?action=get&id=${editId}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    const data = res.data;
                    document.getElementById('category').value = data.category;
                    document.getElementById('purpose').value = data.purpose;
                    document.getElementById('tourDate').value = data.tour_date;
                    document.getElementById('companyName').value = data.company_name;
                    document.getElementById('routingCourse').value = data.routing_course;
                    // Load route into builder after route builder is initialized
                    setTimeout(() => { if(typeof loadExistingRoute === 'function') loadExistingRoute(); }, 100);
                    
                    if(data.souvenir) {
                        const souvenirs = data.souvenir.split(', ');
                        document.querySelectorAll('input[name="souvenir"]').forEach(cb => {
                            if(souvenirs.includes(cb.value)) cb.checked = true;
                        });
                    }

                    const visitorsTbody = document.getElementById('visitorsTbody');
                    visitorsTbody.innerHTML = '';
                    data.visitors.forEach(v => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><input type="text" name="visitorName[]" value="${v.fullname}" required></td>
                            <td><input type="text" name="visitorTitle[]" value="${v.job_title}" required></td>
                            <td><button type="button" class="btn btn-sm btn-danger remove-btn"><i data-lucide="trash-2" style="width:16px;height:16px;"></i></button></td>
                        `;
                        visitorsTbody.appendChild(tr);
                    });
                    if(data.visitors.length === 0) addVisitorRow();

                    const agendaTbody = document.getElementById('agendaTbody');
                    agendaTbody.innerHTML = '';
                    data.schedules.forEach(s => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><input type="time" name="agendaStart[]" value="${s.start_time}" required></td>
                            <td><input type="time" name="agendaEnd[]" value="${s.end_time}" required></td>
                            <td><input type="text" name="agendaActivity[]" value="${s.activity}" required></td>
                            <td><input type="text" name="agendaRemark[]" value="${s.remark}"></td>
                            <td><button type="button" class="btn btn-sm btn-danger remove-btn"><i data-lucide="trash-2" style="width:16px;height:16px;"></i></button></td>
                        `;
                        agendaTbody.appendChild(tr);
                    });
                    if(data.schedules.length === 0) addAgendaRow();

                    const tcpMembers = data.tcp_members || [];
                    const tcpTbodyEdit = document.getElementById('tcpTbody');
                    tcpTbodyEdit.innerHTML = '';
                    tcpMembers.forEach(name => {
                        addTcpRow(name, '');
                    });
                    if(tcpMembers.length === 0) addTcpRow();
                    
                    lucide.createIcons();
                } else {
                    alert("Error loading request: " + res.message);
                }
            });
    }

    // --- BOOKING CALENDAR LOGIC ---
    let calYear = new Date().getFullYear();
    let calMonth = new Date().getMonth(); // 0-indexed
    let calBookings = [];
    let hasDateConflict = false;

    const calGrid = document.getElementById('calendarGrid');
    const calMonthYear = document.getElementById('calMonthYear');
    const calPrev = document.getElementById('calPrev');
    const calNext = document.getElementById('calNext');
    const tourDateInput = document.getElementById('tourDate');
    const dateConflictWarning = document.getElementById('dateConflictWarning');
    const conflictMessage = document.getElementById('conflictMessage');
    const bookingDetails = document.getElementById('calendarBookingDetails');

    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    async function fetchCalendarData() {
        try {
            const m = String(calMonth + 1).padStart(2, '0');
            const res = await fetch(`api_calendar.php?action=month&year=${calYear}&month=${m}`);
            const result = await res.json();
            if (result.status === 'success') {
                calBookings = result.data;
            }
        } catch (e) {
            console.error('Calendar fetch error:', e);
        }
        renderCalendar();
    }

    function renderCalendar() {
        calMonthYear.textContent = `${monthNames[calMonth]} ${calYear}`;
        calGrid.innerHTML = '';

        // Day headers
        dayNames.forEach(d => {
            const header = document.createElement('div');
            header.className = 'cal-day-header';
            header.textContent = d;
            calGrid.appendChild(header);
        });

        const firstDay = new Date(calYear, calMonth, 1).getDay();
        const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const selectedDate = tourDateInput.value;

        // Empty cells before first day
        for (let i = 0; i < firstDay; i++) {
            const empty = document.createElement('div');
            empty.className = 'cal-day cal-empty';
            calGrid.appendChild(empty);
        }

        // Day cells
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${calYear}-${String(calMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dateObj = new Date(calYear, calMonth, day);
            const cell = document.createElement('div');
            cell.className = 'cal-day';
            cell.textContent = day;

            // Past days
            if (dateObj < today) {
                cell.classList.add('cal-past');
            }

            // Today
            if (dateObj.getTime() === today.getTime()) {
                cell.classList.add('cal-today');
            }

            // Selected
            if (dateStr === selectedDate) {
                cell.classList.add('cal-selected');
            }

            // Check bookings for this date
            const dayBookings = calBookings.filter(b => b.tour_date === dateStr);
            if (dayBookings.length > 0) {
                const hasApproved = dayBookings.some(b => b.status === 'Final Approved');
                const hasPending = dayBookings.some(b => b.status === 'Pending PM' || b.status === 'Pending MD');
                
                if (hasApproved) {
                    cell.classList.add('cal-booked-approved');
                    const badge = document.createElement('span');
                    badge.className = 'cal-badge badge-approved';
                    badge.textContent = dayBookings.length > 1 ? `${dayBookings.length} booked` : 'Booked';
                    cell.appendChild(badge);
                } else if (hasPending) {
                    cell.classList.add('cal-booked-pending');
                    const badge = document.createElement('span');
                    badge.className = 'cal-badge badge-pending';
                    badge.textContent = dayBookings.length > 1 ? `${dayBookings.length} pending` : 'Pending';
                    cell.appendChild(badge);
                }
            }

            // Click to select date
            if (dateObj >= today) {
                cell.addEventListener('click', () => {
                    tourDateInput.value = dateStr;
                    checkDateConflict(dateStr);
                    renderCalendar(); // Re-render to update selected highlight

                    // Show booking details if date has bookings
                    if (dayBookings.length > 0) {
                        showBookingDetails(dateStr, dayBookings);
                    } else {
                        bookingDetails.style.display = 'none';
                    }
                });
            }

            calGrid.appendChild(cell);
        }

        lucide.createIcons();
    }

    function showBookingDetails(dateStr, bookings) {
        let html = `<div class="cal-booking-details">
            <div style="font-weight:600; margin-bottom:8px;">📅 ${dateStr} — ${bookings.length} booking(s)</div>`;
        bookings.forEach(b => {
            const statusColor = b.status === 'Final Approved' ? 'background:#dbeafe;color:#1e40af;' : 'background:#fef3c7;color:#92400e;';
            const timeInfo = b.time_slots ? `<span style="color:var(--text-muted);font-size:0.8rem;">🕐 ${b.time_slots}</span>` : '';
            html += `<div class="cal-booking-item">
                <span style="flex:1;font-weight:500;">${b.company_name}</span>
                ${timeInfo}
                <span class="cal-booking-status" style="${statusColor}">${b.status}</span>
            </div>`;
        });
        html += '</div>';
        bookingDetails.innerHTML = html;
        bookingDetails.style.display = 'block';
    }

    async function checkDateConflict(date) {
        if (!date) {
            dateConflictWarning.style.display = 'none';
            hasDateConflict = false;
            return;
        }
        try {
            const excludeParam = isEditMode ? `&exclude_id=${editId}` : '';
            const res = await fetch(`api_calendar.php?action=check&date=${date}${excludeParam}`);
            const result = await res.json();

            if (result.status === 'success' && result.count > 0) {
                hasDateConflict = false; // No longer a hard block - time overlap is checked server-side
                const names = result.bookings.map(b => b.company_name).join(', ');
                conflictMessage.innerHTML = `<strong>📋 Note:</strong> This date already has ${result.count} booking(s): <em>${names}</em>. You can still book if your time slots don't overlap.`;
                dateConflictWarning.style.display = 'block';
                dateConflictWarning.style.background = '#fffbeb';
                dateConflictWarning.style.borderColor = '#fcd34d';
                dateConflictWarning.style.color = '#92400e';
                lucide.createIcons();
            } else {
                hasDateConflict = false;
                dateConflictWarning.style.display = 'none';
            }
        } catch (e) {
            console.error('Date check error:', e);
        }
    }

    // Listen for manual date input change
    tourDateInput.addEventListener('change', (e) => {
        const val = e.target.value;
        checkDateConflict(val);
        // Navigate calendar to that month
        if (val) {
            const d = new Date(val);
            calYear = d.getFullYear();
            calMonth = d.getMonth();
            fetchCalendarData();
        }
    });

    calPrev.addEventListener('click', () => {
        calMonth--;
        if (calMonth < 0) { calMonth = 11; calYear--; }
        fetchCalendarData();
    });

    calNext.addEventListener('click', () => {
        calMonth++;
        if (calMonth > 11) { calMonth = 0; calYear++; }
        fetchCalendarData();
    });

    // Initial calendar load
    fetchCalendarData();

    // --- STEPPER LOGIC ---
    let currentStep = 1;
    const totalSteps = 4; // Now 4 steps including Confirmation

    const btnNext = document.getElementById('btnNext');
    const btnPrev = document.getElementById('btnPrev');
    const btnSubmit = document.getElementById('btnSubmit');
    let isTransitioning = false;

    function updateStepper(direction = 'next') {
        // Handle Step content visibility with slide animation
        document.querySelectorAll('.step-content').forEach((el, index) => {
            const stepNum = index + 1;
            if (stepNum === currentStep) {
                el.classList.remove('slide-out-left', 'slide-out-right', 'slide-in-left');
                el.classList.add('active');
                if (direction === 'next') {
                    el.style.animation = 'none';
                    el.offsetHeight; // trigger reflow
                    el.style.animation = '';
                    el.style.animationName = 'slideInRight';
                } else {
                    el.style.animation = 'none';
                    el.offsetHeight;
                    el.style.animation = '';
                    el.style.animationName = 'slideInLeft';
                }
            } else {
                el.classList.remove('active', 'slide-out-left', 'slide-out-right', 'slide-in-left');
                el.style.animationName = '';
            }
        });

        // Handle Indicators
        document.querySelectorAll('.step-indicator').forEach((el, index) => {
            const stepNum = index + 1;
            el.classList.remove('active', 'completed');
            if (stepNum === currentStep) {
                el.classList.add('active');
            } else if (stepNum < currentStep) {
                el.classList.add('completed');
                el.innerHTML = `<i data-lucide="check" style="width:20px;height:20px;margin-top:2px;"></i><span class="step-label">${el.querySelector('.step-label').innerText}</span>`;
            } else {
                el.innerHTML = `${stepNum}<span class="step-label">${el.querySelector('.step-label').innerText}</span>`;
            }
        });

        // Re-init lucide icons after injecting HTML
        lucide.createIcons();

        // Handle Buttons
        if (currentStep === 1) {
            btnPrev.style.display = 'none';
        } else {
            btnPrev.style.display = 'inline-flex';
        }

        if (currentStep === totalSteps) {
            btnNext.style.display = 'none';
            btnSubmit.style.display = 'inline-flex';
        } else {
            btnNext.style.display = 'inline-flex';
            btnSubmit.style.display = 'none';
        }
    }

    function validateStep(step) {
        // Step 4 (Confirmation) doesn't need validation
        if (step === 4) return true;

        const stepElement = document.getElementById(`step-${step}`);
        const inputs = stepElement.querySelectorAll('input[required], select[required], textarea[required]');
        
        let isValid = true;
        inputs.forEach(input => {
            if (!input.checkValidity()) {
                input.reportValidity();
                isValid = false;
            }
        });
        return isValid;
    }

    // Populate Confirmation Step
    function populateConfirmation() {
        // General Info
        const category = document.getElementById('category');
        const purpose = document.getElementById('purpose');
        const categoryText = category.options[category.selectedIndex]?.text || '';
        const purposeText = purpose.options[purpose.selectedIndex]?.text || '';
        const tourDate = document.getElementById('tourDate').value;
        const companyName = document.getElementById('companyName').value;

        document.getElementById('confirmGeneral').innerHTML = `
            <div class="confirm-item">
                <div class="confirm-label">Category</div>
                <div class="confirm-value">${categoryText}</div>
            </div>
            <div class="confirm-item">
                <div class="confirm-label">Purpose</div>
                <div class="confirm-value">${purposeText}</div>
            </div>
            <div class="confirm-item">
                <div class="confirm-label">Tour Date</div>
                <div class="confirm-value">${tourDate}</div>
            </div>
            <div class="confirm-item">
                <div class="confirm-label">Company</div>
                <div class="confirm-value">${companyName}</div>
            </div>
        `;

        // Visitors
        const visitorRows = document.querySelectorAll('#visitorsTbody tr');
        let visitorHtml = '';
        visitorRows.forEach((row, i) => {
            const name = row.querySelector('input[name="visitorName[]"]')?.value || '';
            const title = row.querySelector('input[name="visitorTitle[]"]')?.value || '';
            if (name) {
                visitorHtml += `<tr><td>${i + 1}</td><td>${name}</td><td>${title}</td></tr>`;
            }
        });
        document.getElementById('confirmVisitorsTbody').innerHTML = visitorHtml || '<tr><td colspan="3" style="text-align:center;color:var(--text-muted);">No visitors added</td></tr>';

        // Agenda
        const agendaRows = document.querySelectorAll('#agendaTbody tr');
        let agendaHtml = '';
        agendaRows.forEach(row => {
            const start = row.querySelector('input[name="agendaStart[]"]')?.value || '';
            const end = row.querySelector('input[name="agendaEnd[]"]')?.value || '';
            const activity = row.querySelector('input[name="agendaActivity[]"]')?.value || '';
            const remark = row.querySelector('input[name="agendaRemark[]"]')?.value || '';
            if (activity) {
                agendaHtml += `<tr><td>${start} - ${end}</td><td>${activity}</td><td>${remark || '-'}</td></tr>`;
            }
        });
        document.getElementById('confirmAgendaTbody').innerHTML = agendaHtml || '<tr><td colspan="3" style="text-align:center;color:var(--text-muted);">No agenda items</td></tr>';

        // Routing & Others
        const routing = document.getElementById('routingCourse').value || 'Not specified';
        const souvenirs = Array.from(document.querySelectorAll('input[name="souvenir"]:checked')).map(cb => cb.value).join(', ') || 'None';
        const tcpRows = document.querySelectorAll('#tcpTbody tr');
        const tcpNames = [];
        tcpRows.forEach(row => {
            const nameInput = row.querySelector('input[name="tcpName[]"]');
            if (nameInput && nameInput.value.trim()) tcpNames.push(nameInput.value.trim());
        });
        const tcpMembers = tcpNames.join(', ') || 'None selected';

        document.getElementById('confirmRouting').innerHTML = `
            <div class="confirm-item" style="grid-column: 1 / -1;">
                <div class="confirm-label">Course of Plant Tour</div>
                <div class="confirm-value">${routing}</div>
            </div>
            <div class="confirm-item">
                <div class="confirm-label">Souvenir</div>
                <div class="confirm-value">${souvenirs}</div>
            </div>
            <div class="confirm-item">
                <div class="confirm-label">TCP Hosts</div>
                <div class="confirm-value">${tcpMembers}</div>
            </div>
        `;
    }

    btnNext.addEventListener('click', () => {
        if (isTransitioning) return;
        if (validateStep(currentStep)) {
            isTransitioning = true;
            currentStep++;
            // Populate confirmation when entering step 4
            if (currentStep === totalSteps) {
                populateConfirmation();
            }
            updateStepper('next');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setTimeout(() => { isTransitioning = false; }, 650);
        }
    });

    btnPrev.addEventListener('click', () => {
        if (isTransitioning) return;
        isTransitioning = true;
        currentStep--;
        updateStepper('prev');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => { isTransitioning = false; }, 650);
    });

    // --- DYNAMIC TABLES LOGIC ---

    // 1. Visitors Table
    const visitorsTbody = document.getElementById('visitorsTbody');
    const addVisitorBtn = document.getElementById('addVisitorBtn');

    function addVisitorRow() {
        const tr = document.createElement('tr');
        tr.style.animation = 'fadeIn 0.3s ease forwards';
        tr.innerHTML = `
            <td><input type="text" name="visitorName[]" placeholder="e.g. John Doe" required></td>
            <td><input type="text" name="visitorTitle[]" placeholder="e.g. CEO" required></td>
            <td>
                <button type="button" class="btn btn-sm btn-danger remove-btn">
                    <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
                </button>
            </td>
        `;
        visitorsTbody.appendChild(tr);
        lucide.createIcons();
    }

    // Add initially 1 row
    addVisitorRow();

    addVisitorBtn.addEventListener('click', addVisitorRow);

    visitorsTbody.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-btn');
        if (btn) {
            const rowCount = visitorsTbody.querySelectorAll('tr').length;
            if(rowCount > 1) {
                const row = btn.closest('tr');
                row.style.animation = 'slideOutLeft 0.3s ease forwards';
                setTimeout(() => row.remove(), 300);
            } else {
                alert("You need at least one visitor.");
            }
        }
    });

    // 2. Agenda Table
    const agendaTbody = document.getElementById('agendaTbody');
    const addAgendaBtn = document.getElementById('addAgendaBtn');

    function addAgendaRow() {
        const tr = document.createElement('tr');
        tr.style.animation = 'fadeIn 0.3s ease forwards';
        tr.innerHTML = `
            <td><input type="time" name="agendaStart[]" required></td>
            <td><input type="time" name="agendaEnd[]" required></td>
            <td><input type="text" name="agendaActivity[]" placeholder="e.g. Plant Tour" required></td>
            <td><input type="text" name="agendaRemark[]" placeholder="Optional"></td>
            <td>
                <button type="button" class="btn btn-sm btn-danger remove-btn">
                    <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
                </button>
            </td>
        `;
        agendaTbody.appendChild(tr);
        lucide.createIcons();
    }

    addAgendaRow();

    addAgendaBtn.addEventListener('click', addAgendaRow);

    agendaTbody.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-btn');
        if (btn) {
            const rowCount = agendaTbody.querySelectorAll('tr').length;
            if(rowCount > 1) {
                const row = btn.closest('tr');
                row.style.animation = 'slideOutLeft 0.3s ease forwards';
                setTimeout(() => row.remove(), 300);
            } else {
                alert("You need at least one agenda item.");
            }
        }
    });

    // --- ROUTE BUILDER LOGIC ---
    let selectedRoute = [];
    const routeSelectedList = document.getElementById('routeSelectedList');
    const routeSelectedHeader = document.getElementById('routeSelectedHeader');
    const routeDiagramWrapper = document.getElementById('routeDiagramWrapper');
    const routeDiagram = document.getElementById('routeDiagram');
    const routingCourseInput = document.getElementById('routingCourse');
    const routePresetChips = document.getElementById('routePresetChips');
    const customLocationInput = document.getElementById('customLocationInput');
    const addCustomLocationBtn = document.getElementById('addCustomLocationBtn');
    const clearRouteBtn = document.getElementById('clearRouteBtn');

    function addLocationToRoute(location) {
        selectedRoute.push(location);
        updateRouteUI();
    }

    function removeLocationFromRoute(index) {
        selectedRoute.splice(index, 1);
        updateRouteUI();
    }

    function updateRouteUI() {
        // Update hidden input
        routingCourseInput.value = selectedRoute.join(' -> ');

        // Update chip states
        routePresetChips.querySelectorAll('.route-chip').forEach(chip => {
            const loc = chip.getAttribute('data-location');
            if (selectedRoute.includes(loc)) {
                chip.classList.add('chip-selected');
            } else {
                chip.classList.remove('chip-selected');
            }
        });

        // Show/hide sections
        if (selectedRoute.length > 0) {
            routeSelectedHeader.style.display = 'flex';
            routeDiagramWrapper.style.display = 'block';
        } else {
            routeSelectedHeader.style.display = 'none';
            routeDiagramWrapper.style.display = 'none';
        }

        // Render selected list
        routeSelectedList.innerHTML = '';
        selectedRoute.forEach((loc, i) => {
            const item = document.createElement('div');
            item.className = 'route-selected-item';
            item.setAttribute('draggable', 'true');
            item.setAttribute('data-index', i);
            item.innerHTML = `
                <span class="route-grip"><i data-lucide="grip-vertical" style="width:14px;height:14px;"></i></span>
                <span class="route-step-num">${i + 1}</span>
                <span style="flex:1;">${loc}</span>
                <button type="button" class="route-remove-btn" data-idx="${i}">
                    <i data-lucide="x" style="width:14px;height:14px;"></i>
                </button>
            `;
            routeSelectedList.appendChild(item);
        });

        // Render flow diagram
        renderRouteDiagram();

        lucide.createIcons();
        setupDragAndDrop();
    }

    function renderRouteDiagram() {
        routeDiagram.innerHTML = '';
        selectedRoute.forEach((loc, i) => {
            // Node
            const node = document.createElement('div');
            node.className = 'route-node';
            const label = loc.length > 12 ? loc.substring(0, 11) + '…' : loc;
            node.innerHTML = `
                <div class="route-node-circle">${i + 1}</div>
                <div class="route-node-label">${loc}</div>
            `;
            routeDiagram.appendChild(node);

            // Arrow (except after last)
            if (i < selectedRoute.length - 1) {
                const arrow = document.createElement('div');
                arrow.className = 'route-arrow';
                arrow.innerHTML = '<div class="route-arrow-line"></div>';
                routeDiagram.appendChild(arrow);
            }
        });
    }

    function setupDragAndDrop() {
        const items = routeSelectedList.querySelectorAll('.route-selected-item');
        let dragSrcIndex = null;

        items.forEach(item => {
            item.addEventListener('dragstart', (e) => {
                dragSrcIndex = parseInt(item.getAttribute('data-index'));
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                item.style.borderColor = 'var(--primary)';
            });

            item.addEventListener('dragleave', () => {
                item.style.borderColor = '';
            });

            item.addEventListener('drop', (e) => {
                e.preventDefault();
                const dropIndex = parseInt(item.getAttribute('data-index'));
                if (dragSrcIndex !== null && dragSrcIndex !== dropIndex) {
                    const moved = selectedRoute.splice(dragSrcIndex, 1)[0];
                    selectedRoute.splice(dropIndex, 0, moved);
                    updateRouteUI();
                }
                item.style.borderColor = '';
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
            });
        });
    }

    // Preset chip clicks
    routePresetChips.addEventListener('click', (e) => {
        const chip = e.target.closest('.route-chip');
        if (!chip) return;
        const location = chip.getAttribute('data-location');

        if (selectedRoute.includes(location)) {
            // Remove if already selected
            const idx = selectedRoute.indexOf(location);
            selectedRoute.splice(idx, 1);
        } else {
            selectedRoute.push(location);
        }
        updateRouteUI();
    });

    // Custom location
    addCustomLocationBtn.addEventListener('click', () => {
        const val = customLocationInput.value.trim();
        if (val) {
            addLocationToRoute(val);
            customLocationInput.value = '';
        }
    });

    customLocationInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addCustomLocationBtn.click();
        }
    });

    // Remove individual stop
    routeSelectedList.addEventListener('click', (e) => {
        const btn = e.target.closest('.route-remove-btn');
        if (btn) {
            const idx = parseInt(btn.getAttribute('data-idx'));
            removeLocationFromRoute(idx);
        }
    });

    // Clear all
    clearRouteBtn.addEventListener('click', () => {
        selectedRoute = [];
        updateRouteUI();
    });

    // Load existing route (edit mode) from hidden input
    function loadExistingRoute() {
        const existing = routingCourseInput.value;
        if (existing) {
            selectedRoute = existing.split(' -> ').map(s => s.trim()).filter(Boolean);
            updateRouteUI();
        }
    }
    // Will be called after edit mode loads data

    // 3. TCP Members Table
    const tcpTbody = document.getElementById('tcpTbody');
    const addTcpBtn = document.getElementById('addTcpBtn');

    // Preset employees for the dropdown
    const presetEmployees = [
        { name: 'Somchai Sabai', dept: 'Plant Manager' },
        { name: 'Wannipa R.', dept: 'QA Manager' },
        { name: 'Nattapong K.', dept: 'Sales' },
        { name: 'Somsak P.', dept: 'Engineering' },
        { name: 'Praneet S.', dept: 'HR' }
    ];

    function addTcpRow(name = '', dept = '') {
        const tr = document.createElement('tr');
        tr.style.animation = 'fadeIn 0.3s ease forwards';

        // Build dropdown options
        let optionsHtml = '<option value="">-- Select or type below --</option>';
        presetEmployees.forEach(emp => {
            const selected = (name === emp.name) ? 'selected' : '';
            optionsHtml += `<option value="${emp.name}" data-dept="${emp.dept}" ${selected}>${emp.name} (${emp.dept})</option>`;
        });
        optionsHtml += '<option value="__custom__">✏️ Type custom name...</option>';

        const isCustom = name && !presetEmployees.some(e => e.name === name);

        tr.innerHTML = `
            <td>
                <select class="tcp-select" style="margin-bottom:4px;">${optionsHtml}</select>
                <input type="text" name="tcpName[]" placeholder="Employee name" value="${name}" 
                       style="display:${isCustom ? 'block' : 'none'};" ${isCustom ? '' : ''}>
            </td>
            <td>
                <input type="text" name="tcpDept[]" placeholder="e.g. Engineering" value="${dept}" readonly style="color:var(--text-muted);">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger remove-btn">
                    <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
                </button>
            </td>
        `;

        // Wire up dropdown change
        const selectEl = tr.querySelector('.tcp-select');
        const nameInput = tr.querySelector('input[name="tcpName[]"]');
        const deptInput = tr.querySelector('input[name="tcpDept[]"]');

        // If name is a preset, set select value and hide input
        if (name && !isCustom) {
            selectEl.value = name;
            nameInput.value = name;
            nameInput.style.display = 'none';
            deptInput.value = presetEmployees.find(e => e.name === name)?.dept || dept;
        }

        selectEl.addEventListener('change', () => {
            const val = selectEl.value;
            if (val === '__custom__') {
                nameInput.style.display = 'block';
                nameInput.value = '';
                nameInput.focus();
                deptInput.value = '';
                deptInput.readOnly = false;
                deptInput.style.color = 'var(--text-dark)';
            } else if (val) {
                nameInput.style.display = 'none';
                nameInput.value = val;
                const emp = presetEmployees.find(e => e.name === val);
                deptInput.value = emp ? emp.dept : '';
                deptInput.readOnly = true;
                deptInput.style.color = 'var(--text-muted)';
            } else {
                nameInput.style.display = 'none';
                nameInput.value = '';
                deptInput.value = '';
            }
        });

        tcpTbody.appendChild(tr);
        lucide.createIcons();
    }

    // Add 1 initial row (only if not edit mode - edit mode populates its own)
    if (!isEditMode) addTcpRow();

    addTcpBtn.addEventListener('click', () => addTcpRow());

    tcpTbody.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-btn');
        if (btn) {
            const rowCount = tcpTbody.querySelectorAll('tr').length;
            if (rowCount > 1) {
                const row = btn.closest('tr');
                row.style.animation = 'slideOutLeft 0.3s ease forwards';
                setTimeout(() => row.remove(), 300);
            } else {
                alert('You need at least one TCP member.');
            }
        }
    });

    // --- FORM SUBMIT LOGIC ---
    document.getElementById('btnSubmit').addEventListener('click', async () => {
        if (!validateStep(currentStep)) return;

        // Collect Form Data
        const form = document.getElementById('visitorForm');
        const formData = new FormData(form);

        // Collect TCP Members from dynamic table
        const tcpNameInputs = document.querySelectorAll('input[name="tcpName[]"]');
        const tcpMembersList = [];
        tcpNameInputs.forEach(input => {
            const val = input.value.trim();
            const dept = input.closest('tr')?.querySelector('input[name="tcpDept[]"]')?.value || '';
            if (val) tcpMembersList.push(dept ? `${val} (${dept})` : val);
        });
        formData.delete('tcpName[]');
        formData.delete('tcpDept[]');
        formData.delete('tcp-select');
        formData.append('tcpMembers', tcpMembersList.join(', '));

        // Submit Logic (Real API Call)
        const btn = document.getElementById('btnSubmit');
        btn.innerHTML = `<i data-lucide="loader" class="spin" style="width:18px;height:18px;"></i> Processing...`;
        btn.disabled = true;
        lucide.createIcons();
        
        try {
            const endpoint = isEditMode ? 'update_request.php' : 'submit_request.php';
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.status === 'success') {
                // Hide form and stepper header
                document.getElementById('visitorForm').style.display = 'none';
                document.querySelector('.stepper-header').style.display = 'none';
                document.querySelector('header p').style.display = 'none';
                
                // Show success container and set ID
                document.getElementById('displayRequestId').innerText = 'VR-' + String(result.request_id).padStart(4, '0');
                document.getElementById('successContainer').style.display = 'block';
                
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                alert("Error: " + result.message);
                btn.innerHTML = isEditMode ? `Update Request <i data-lucide="save" style="width:18px;height:18px;"></i>` : `Submit Request <i data-lucide="check-circle" style="width:18px;height:18px;"></i>`;
                btn.disabled = false;
                lucide.createIcons();
            }
        } catch (error) {
            console.error("Submission Error:", error);
            alert("An error occurred during submission.");
            const btn = document.getElementById('btnSubmit');
            btn.innerHTML = isEditMode ? `Update Request <i data-lucide="save" style="width:18px;height:18px;"></i>` : `Submit Request <i data-lucide="check-circle" style="width:18px;height:18px;"></i>`;
            btn.disabled = false;
            lucide.createIcons();
        }
    });

});
