async function run(action) {
    const output = document.getElementById('output');
    const panelEl = document.getElementById('panel');
    const panel = panelEl ? panelEl.value : 'all';

    output.textContent = '⏳ Running ' + action + ' for ' + panel + '...';

    // Global variable from PHP
    const ajaxUrl = typeof AJAX_URL !== 'undefined' ? AJAX_URL : '/cleanup/ajax';

    try {
        const resText = await fetch(ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=' + encodeURIComponent(action) + '&panel=' + encodeURIComponent(panel)
        }).then(r => r.text());

        const data = JSON.parse(resText);

        if (data.status === 'success') {
            // Display the main message
            output.innerHTML = data.message.replace(/\\n/g, '<br>');

            // If there's data object, display it formatted
            if (data.data && typeof data.data === 'object') {
                output.innerHTML += '<br><br><strong>Details:</strong><br>';
                output.innerHTML += formatData(data.data);
            }
        } else {
            output.innerHTML = '<span style="color:red;">❌ ' + data.message + '</span>';
        }
    } catch (err) {
        output.textContent = '❌ Error: ' + err.message;
    }
}

// Smart function to format any data structure
function formatData(data, indent = 0) {
    let html = '';
    const indentStr = '&nbsp;'.repeat(indent * 4);

    if (Array.isArray(data)) {
        // Handle arrays
        html += '<div style="margin-left: ' + (indent * 20) + 'px;">';
        data.forEach((item, index) => {
            html += `<div>${indentStr}• ${formatData(item, indent + 1)}</div>`;
        });
        html += '</div>';
    } else if (typeof data === 'object' && data !== null) {
        // Handle objects
        html += '<div style="margin-left: ' + (indent * 20) + 'px;">';
        for (const [key, value] of Object.entries(data)) {
            if (typeof value === 'object' && value !== null) {
                // Nested object/array
                html += `<div><strong>${key}:</strong></div>`;
                html += formatData(value, indent + 1);
            } else {
                // Simple value
                html += `<div>${indentStr}<strong>${key}:</strong> ${formatValue(value)}</div>`;
            }
        }
        html += '</div>';
    } else {
        // Handle simple values
        html += formatValue(data);
    }

    return html;
}

// Format individual values with styling
function formatValue(value) {
    if (value === null) return '<em style="color: #666;">null</em>';
    if (value === undefined) return '<em style="color: #666;">undefined</em>';
    if (typeof value === 'boolean') return `<code style="color: #d33682;">${value}</code>`;
    if (typeof value === 'number') return `<code style="color: #2aa198;">${value}</code>`;

    // String values - check for emojis/symbols and color code
    const strValue = String(value);
    if (strValue.includes('✅') || strValue.includes('✔') || strValue.includes('✓')) {
        return `<span style="color: #859900;">${strValue}</span>`;
    } else if (strValue.includes('❌') || strValue.includes('✖') || strValue.includes('⚠')) {
        return `<span style="color: #dc322f;">${strValue}</span>`;
    } else if (strValue.includes('ℹ') || strValue.includes('🔍')) {
        return `<span style="color: #268bd2;">${strValue}</span>`;
    }

    return `<span>${strValue}</span>`;
}


