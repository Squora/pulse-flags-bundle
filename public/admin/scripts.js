// ==================== Configuration ====================
// Expected global variables (injected from Twig template):
// - allFlagsData: Array of flag objects from server
// - editingFlag: Currently edited flag (null initially)
// - isRendering: Boolean to prevent concurrent renders
// - adminConfig: { requireConfirmation: boolean, csrfToken: string, assetsUrl: string }
// - API_ENDPOINTS: { list, toggle, update, create, delete }

// Note: Variables are declared in the HTML template, not here

// ==================== Statistics Functions ====================
/**
 * Update statistics cards with current flag counts
 */
function updateStats() {
    const totalFlags = allFlagsData.length;
    const enabledFlags = allFlagsData.filter(flag => flag.enabled).length;
    const writableFlags = allFlagsData.filter(flag => !flag.readonly).length;

    document.getElementById('total-flags').textContent = totalFlags;
    document.getElementById('enabled-flags').textContent = enabledFlags;
    document.getElementById('writable-flags').textContent = writableFlags;
}

// ==================== API Client Functions ====================
/**
 * Load flags from server API
 */
async function loadFlags() {
    try {
        const response = await fetch(API_ENDPOINTS.list);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        allFlagsData = await response.json();
        updateStats();
        renderFlags();
    } catch (error) {
        console.error('Error loading flags:', error);
        showAlert('Failed to load flags: ' + error.message, 'error');
    }
}

/**
 * Toggle flag enabled status
 * @param {string} name - Flag name
 */
async function toggleFlag(name) {
    const flag = allFlagsData.find(f => f.name === name);
    if (!flag) return;

    if (flag.readonly) {
        showAlert('Cannot modify read-only flag', 'error');
        return;
    }

    if (adminConfig.requireConfirmation) {
        const action = flag.enabled ? 'disable' : 'enable';
        showConfirmDialog(
            `Are you sure you want to ${action} flag "${name}"?`,
            async () => await performToggle(name, !flag.enabled)
        );
    } else {
        await performToggle(name, !flag.enabled);
    }
}

/**
 * Perform the actual toggle operation
 * @param {string} name - Flag name
 * @param {boolean} enable - New enabled state
 */
async function performToggle(name, enable) {
    try {
        const url = API_ENDPOINTS.toggle.replace('__NAME__', encodeURIComponent(name));
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminConfig.csrfToken
            },
            body: JSON.stringify({ enabled: enable })
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to toggle flag');
        }

        const result = await response.json();
        const flagIndex = allFlagsData.findIndex(f => f.name === name);
        if (flagIndex !== -1) {
            allFlagsData[flagIndex] = result;
        }

        updateStats();
        renderFlags();
        showAlert(`Flag "${name}" ${enable ? 'enabled' : 'disabled'} successfully`, 'success');
    } catch (error) {
        console.error('Error toggling flag:', error);
        showAlert('Failed to toggle flag: ' + error.message, 'error');
    }
}

/**
 * Save flag configuration (create or update)
 * @param {Object} data - Flag data
 */
async function saveFlag(data) {
    try {
        let response;
        if (editingFlag) {
            const url = API_ENDPOINTS.update.replace('__NAME__', encodeURIComponent(editingFlag.name));
            response = await fetch(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': adminConfig.csrfToken
                },
                body: JSON.stringify(data)
            });
        } else {
            response = await fetch(API_ENDPOINTS.create, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': adminConfig.csrfToken
                },
                body: JSON.stringify(data)
            });
        }

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to save flag');
        }

        const result = await response.json();

        if (editingFlag) {
            const index = allFlagsData.findIndex(f => f.name === editingFlag.name);
            if (index !== -1) {
                allFlagsData[index] = result;
            }
        } else {
            allFlagsData.push(result);
        }

        updateStats();
        renderFlags();
        closeFlagModal();
        showAlert(`Flag ${editingFlag ? 'updated' : 'created'} successfully`, 'success');
    } catch (error) {
        console.error('Error saving flag:', error);
        showAlert('Failed to save flag: ' + error.message, 'error');
    }
}

/**
 * Delete a flag
 * @param {string} name - Flag name
 */
async function deleteFlag(name) {
    const flag = allFlagsData.find(f => f.name === name);
    if (!flag) return;

    if (flag.readonly) {
        showAlert('Cannot delete read-only flag', 'error');
        return;
    }

    showConfirmDialog(
        `Are you sure you want to delete flag "${name}"? This action cannot be undone.`,
        async () => {
            try {
                const url = API_ENDPOINTS.delete.replace('__NAME__', encodeURIComponent(name));
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-Token': adminConfig.csrfToken
                    }
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Failed to delete flag');
                }

                allFlagsData = allFlagsData.filter(f => f.name !== name);
                updateStats();
                renderFlags();
                showAlert(`Flag "${name}" deleted successfully`, 'success');
            } catch (error) {
                console.error('Error deleting flag:', error);
                showAlert('Failed to delete flag: ' + error.message, 'error');
            }
        }
    );
}

// ==================== UI Rendering Functions ====================
/**
 * Group flags by section prefix
 * @param {Array} flags - Array of flags
 * @returns {Object} Flags grouped by section
 */
function groupFlagsBySection(flags) {
    const sections = {};

    flags.forEach(flag => {
        const parts = flag.name.split('.');
        const section = parts.length > 1 ? parts[0] : 'general';

        if (!sections[section]) {
            sections[section] = [];
        }

        sections[section].push(flag);
    });

    return sections;
}

/**
 * Get section information (icon, title, description)
 * @param {string} sectionName - Section name
 * @returns {Object} Section info
 */
function getSectionInfo(sectionName) {
    const sectionInfo = {
        'core': { icon: 'C', title: 'Core Features', description: 'Essential system functionality' },
        'ui': { icon: 'U', title: 'User Interface', description: 'Visual elements and user experience' },
        'api': { icon: 'A', title: 'API Features', description: 'Backend and integration features' },
        'payment': { icon: 'P', title: 'Payment System', description: 'Billing and payment processing' },
        'analytics': { icon: '📊', title: 'Analytics', description: 'Data tracking and reporting' },
        'notification': { icon: '🔔', title: 'Notifications', description: 'User alerts and messaging' },
        'general': { icon: 'G', title: 'General', description: 'Uncategorized features' }
    };

    return sectionInfo[sectionName] || {
        icon: sectionName.charAt(0).toUpperCase(),
        title: sectionName.charAt(0).toUpperCase() + sectionName.slice(1),
        description: `${sectionName} related features`
    };
}

/**
 * Render all flags in their respective tabs
 */
function renderFlags() {
    if (isRendering) return;
    isRendering = true;

    const allContainer = document.getElementById('all-flags');
    const persistentContainer = document.getElementById('persistent-flags');
    const permanentContainer = document.getElementById('permanent-flags');

    allContainer.innerHTML = '';
    persistentContainer.innerHTML = '';
    permanentContainer.innerHTML = '';

    const allSections = groupFlagsBySection(allFlagsData);
    const persistentFlags = allFlagsData.filter(f => !f.readonly);
    const permanentFlags = allFlagsData.filter(f => f.readonly);

    renderSections(allContainer, allSections);

    if (persistentFlags.length > 0) {
        const persistentSections = groupFlagsBySection(persistentFlags);
        renderSections(persistentContainer, persistentSections);
    } else {
        persistentContainer.innerHTML = '<div class="empty-state"><p>No writable flags configured yet</p></div>';
    }

    if (permanentFlags.length > 0) {
        const permanentSections = groupFlagsBySection(permanentFlags);
        renderSections(permanentContainer, permanentSections);
    } else {
        permanentContainer.innerHTML = '<div class="empty-state"><p>No read-only flags configured yet</p></div>';
    }

    isRendering = false;
}

/**
 * Render sections with flags
 * @param {HTMLElement} container - Container element
 * @param {Object} sections - Sections object
 */
function renderSections(container, sections) {
    Object.keys(sections).sort().forEach(sectionName => {
        const sectionInfo = getSectionInfo(sectionName);
        const section = document.createElement('div');
        section.className = 'section';

        section.innerHTML = `
            <div class="section-header">
                <div class="section-icon">${sectionInfo.icon}</div>
                <div>
                    <div class="section-title">${sectionInfo.title}</div>
                    <div class="section-description">${sectionInfo.description}</div>
                </div>
            </div>
            <div class="flag-grid"></div>
        `;

        container.appendChild(section);

        const grid = section.querySelector('.flag-grid');
        sections[sectionName].forEach(flag => {
            const card = createFlagCard(flag);
            grid.appendChild(card);
        });
    });
}

/**
 * Create a flag card element
 * @param {Object} flag - Flag object
 * @returns {HTMLElement} Card element
 */
function createFlagCard(flag) {
    const card = document.createElement('div');
    card.className = flag.readonly ? 'flag-card flag-readonly' : 'flag-card flag-writable';
    card.setAttribute('data-flag-name', flag.name);

    const strategyInfo = getStrategyInfo(flag);
    const parts = flag.name.split('.');
    const prefix = parts.length > 1 ? parts[0] : 'general';
    const nameWithoutPrefix = parts.length > 1 ? parts.slice(1).join('.') : flag.name;

    card.innerHTML = `
            <div class="flag-header">
                <div style="flex: 1; min-width: 0;">
                    <div class="flag-name">
                        <span class="flag-prefix">${prefix}.</span>${escapeHtml(nameWithoutPrefix)}
                    </div>
                    ${flag.description ? `<div class="flag-description">${escapeHtml(flag.description)}</div>` : ''}
                </div>
                <label class="flag-toggle">
                    <input type="checkbox" ${flag.enabled ? 'checked' : ''}
                           ${flag.readonly ? 'disabled' : ''}
                           onchange="toggleFlag('${escapeHtml(flag.name)}')">
                    <span class="slider ${flag.readonly ? 'disabled' : ''}"></span>
                </label>
            </div>

            <div>
                <span class="flag-badge ${flag.readonly ? 'badge-readonly' : 'badge-writable'}">
                    ${flag.readonly ? 'Read-only' : 'Writable'}
                </span>
                <span class="flag-badge badge-strategy">
                    ${flag.strategy}
                </span>
            </div>

            ${strategyInfo ? `
                <div class="flag-details">
                    ${strategyInfo}
                </div>
            ` : ''}

            ${!flag.readonly ? `
                <div class="actions">
                    <button class="btn btn-primary" onclick="editFlag('${escapeHtml(flag.name)}')">
                        Edit
                    </button>
                    <button class="btn btn-danger" onclick="deleteFlag('${escapeHtml(flag.name)}')">
                        Delete
                    </button>
                </div>
            ` : ''}
        `;

    return card;
}

/**
 * Get strategy-specific information for display
 * @param {Object} flag - Flag object
 * @returns {string} HTML string with strategy info
 */
function getStrategyInfo(flag) {
    const items = [];
    const strategy = flag.strategy || 'simple';

    if (strategy === 'percentage' && flag.config.percentage !== undefined) {
        items.push(`<div class="flag-detail-item">
                <span class="flag-detail-label">Percentage:</span>
                <span>${flag.config.percentage}%</span>
            </div>`);
    }

    if (strategy === 'user_id') {
        if (flag.config.whitelist && flag.config.whitelist.length > 0) {
            items.push(`<div class="flag-detail-item">
                    <span class="flag-detail-label">Whitelist:</span>
                    <span>${flag.config.whitelist.join(', ')}</span>
                </div>`);
        }
        if (flag.config.blacklist && flag.config.blacklist.length > 0) {
            items.push(`<div class="flag-detail-item">
                    <span class="flag-detail-label">Blacklist:</span>
                    <span>${flag.config.blacklist.join(', ')}</span>
                </div>`);
        }
    }

    if (strategy === 'date_range') {
        if (flag.config.start_date) {
            items.push(`<div class="flag-detail-item">
                    <span class="flag-detail-label">Start:</span>
                    <span>${flag.config.start_date}</span>
                </div>`);
        }
        if (flag.config.end_date) {
            items.push(`<div class="flag-detail-item">
                    <span class="flag-detail-label">End:</span>
                    <span>${flag.config.end_date}</span>
                </div>`);
        }
    }

    return items.length > 0 ? items.join('') : null;
}

// ==================== Tab & Search Functions ====================
/**
 * Switch between tabs
 * @param {string} tabName - Tab name (all, persistent, permanent)
 * @param {HTMLElement} element - Tab button element
 */
function switchTab(tabName, element) {
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    element.classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
}

/**
 * Filter flags based on search input
 */
function filterFlags() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const activeTab = document.querySelector('.tab-content.active');
    const flagCards = activeTab.querySelectorAll('.flag-card');

    flagCards.forEach(card => {
        const flagName = card.getAttribute('data-flag-name').toLowerCase();
        const description = card.querySelector('.flag-description')?.textContent.toLowerCase() || '';

        if (flagName.includes(searchTerm) || description.includes(searchTerm)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });

    activeTab.querySelectorAll('.section').forEach(section => {
        const grid = section.querySelector('.flag-grid');
        const visibleCards = grid.querySelectorAll('.flag-card:not([style*="display: none"])');

        if (visibleCards.length === 0) {
            section.style.display = 'none';
        } else {
            section.style.display = '';
        }
    });
}

// ==================== Modal Functions ====================
/**
 * Open modal to create new flag
 */
function openCreateModal() {
    editingFlag = null;
    document.getElementById('modalTitle').textContent = 'Create New Flag';
    document.getElementById('flagForm').reset();
    document.getElementById('flagName').disabled = false;
    updateStrategyFields();
    document.getElementById('flagModal').classList.add('active');
}

/**
 * Open modal to edit existing flag
 * @param {string} name - Flag name
 */
function editFlag(name) {
    const flag = allFlagsData.find(f => f.name === name);
    if (!flag || flag.readonly) {
        showAlert('Cannot edit this flag', 'error');
        return;
    }

    editingFlag = flag;
    document.getElementById('modalTitle').textContent = 'Edit Flag';

    const parts = flag.name.split('.');
    const section = parts.length > 1 ? parts[0] : 'general';
    const nameWithoutSection = parts.length > 1 ? parts.slice(1).join('.') : flag.name;

    document.getElementById('flagSection').value = section;
    document.getElementById('flagName').value = nameWithoutSection;
    document.getElementById('flagName').disabled = true;
    document.getElementById('flagDescription').value = flag.description || '';
    document.getElementById('flagEnabled').checked = flag.enabled;
    document.getElementById('flagStrategy').value = flag.strategy || 'simple';

    if (flag.config.percentage !== undefined) {
        document.getElementById('flagPercentage').value = flag.config.percentage;
    }
    if (flag.config.whitelist) {
        document.getElementById('flagWhitelist').value = flag.config.whitelist.join(', ');
    }
    if (flag.config.blacklist) {
        document.getElementById('flagBlacklist').value = flag.config.blacklist.join(', ');
    }
    if (flag.config.start_date) {
        document.getElementById('flagStartDate').value = flag.config.start_date;
    }
    if (flag.config.end_date) {
        document.getElementById('flagEndDate').value = flag.config.end_date;
    }

    updateStrategyFields();
    document.getElementById('flagModal').classList.add('active');
}

/**
 * Close flag modal
 */
function closeFlagModal() {
    document.getElementById('flagModal').classList.remove('active');
    editingFlag = null;
}

/**
 * Handle flag form submission
 * @param {Event} e - Form submit event
 */
function submitFlagForm(e) {
    e.preventDefault();

    const section = document.getElementById('flagSection').value;
    const name = document.getElementById('flagName').value.trim();
    const fullName = `${section}.${name}`;

    const data = {
        name: fullName,
        description: document.getElementById('flagDescription').value.trim(),
        enabled: document.getElementById('flagEnabled').checked,
        strategy: document.getElementById('flagStrategy').value,
        config: {}
    };

    const strategy = data.strategy;

    if (strategy === 'percentage') {
        data.config.percentage = parseInt(document.getElementById('flagPercentage').value);
    } else if (strategy === 'user_id') {
        const whitelist = document.getElementById('flagWhitelist').value.trim();
        const blacklist = document.getElementById('flagBlacklist').value.trim();

        if (whitelist) {
            data.config.whitelist = whitelist.split(',').map(s => s.trim()).filter(s => s);
        }
        if (blacklist) {
            data.config.blacklist = blacklist.split(',').map(s => s.trim()).filter(s => s);
        }
    } else if (strategy === 'date_range') {
        const startDate = document.getElementById('flagStartDate').value;
        const endDate = document.getElementById('flagEndDate').value;

        if (startDate) data.config.start_date = startDate;
        if (endDate) data.config.end_date = endDate;
    }

    saveFlag(data);
}

/**
 * Update strategy-specific form fields visibility
 */
function updateStrategyFields() {
    const strategy = document.getElementById('flagStrategy').value;

    document.getElementById('percentageGroup').style.display =
        strategy === 'percentage' ? 'block' : 'none';
    document.getElementById('whitelistGroup').style.display =
        strategy === 'user_id' ? 'block' : 'none';
    document.getElementById('blacklistGroup').style.display =
        strategy === 'user_id' ? 'block' : 'none';
    document.getElementById('dateRangeGroup').style.display =
        strategy === 'date_range' ? 'block' : 'none';
}

/**
 * Update full flag name preview
 */
function updateFlagName() {
    const section = document.getElementById('flagSection').value;
    const name = document.getElementById('flagName').value.trim();
    const preview = document.getElementById('flagNamePreview');

    if (preview) {
        preview.textContent = name ? `${section}.${name}` : `${section}.`;
    }
}

// ==================== Confirmation Dialog ====================
/**
 * Show confirmation dialog
 * @param {string} message - Confirmation message
 * @param {Function} onConfirm - Callback on confirm
 */
function showConfirmDialog(message, onConfirm) {
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmModal').classList.add('active');

    const confirmBtn = document.getElementById('confirmYes');
    const cancelBtn = document.getElementById('confirmNo');

    const handleConfirm = async () => {
        document.getElementById('confirmModal').classList.remove('active');
        await onConfirm();
        cleanup();
    };

    const handleCancel = () => {
        document.getElementById('confirmModal').classList.remove('active');
        cleanup();
    };

    const cleanup = () => {
        confirmBtn.removeEventListener('click', handleConfirm);
        cancelBtn.removeEventListener('click', handleCancel);
    };

    confirmBtn.addEventListener('click', handleConfirm);
    cancelBtn.addEventListener('click', handleCancel);
}

// ==================== Alert & Toast Functions ====================
/**
 * Show alert message
 * @param {string} message - Alert message
 * @param {string} type - Alert type (success, error, warning)
 */
function showAlert(message, type = 'success') {
    const alertsContainer = document.getElementById('alerts');
    const alert = document.createElement('div');
    alert.className = `alert ${type}`;
    alert.textContent = message;

    alertsContainer.appendChild(alert);

    setTimeout(() => {
        alert.classList.add('removing');
        setTimeout(() => alert.remove(), 300);
    }, 3000);
}

// ==================== Utility Functions ====================
/**
 * Escape HTML to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ==================== Event Listeners ====================
document.addEventListener('DOMContentLoaded', () => {
    // Setup flag form submission
    document.getElementById('flagForm').addEventListener('submit', submitFlagForm);

    // Close modals on backdrop click
    document.getElementById('flagModal').addEventListener('click', (e) => {
        if (e.target.id === 'flagModal') {
            closeFlagModal();
        }
    });

    document.getElementById('confirmModal').addEventListener('click', (e) => {
        if (e.target.id === 'confirmModal') {
            e.target.classList.remove('active');
        }
    });

    // Initial render with server-provided data
    updateStats();
    renderFlags();
});
