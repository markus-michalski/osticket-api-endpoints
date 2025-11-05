/**
 * API Key Form Extension
 *
 * Dynamically adds "Can Update Tickets" checkbox to API Key admin form
 */
(function() {
    // Only run on API Key pages
    if (!window.location.href.includes('apikeys.php')) {
        return;
    }

    // Wait for DOM to be ready
    $(document).ready(function() {
        // Find the "Can Create Tickets" checkbox row
        var $createRow = $('input[name="can_create_tickets"]').closest('tr');

        if ($createRow.length === 0) {
            console.warn('API Endpoints Plugin: Could not find can_create_tickets checkbox');
            return;
        }

        // Check if our checkbox already exists (avoid duplicates)
        if ($('input[name="can_update_tickets"]').length > 0) {
            return;
        }

        // Get current value from data attribute or hidden input (if editing existing key)
        var currentValue = $('input[name="can_update_tickets_value"]').val() || '0';

        // Create new row for "Can Update Tickets"
        var $newRow = $('<tr>')
            .append(
                $('<td colspan="2" style="padding-left:5px">')
                    .append(
                        $('<label>')
                            .append(
                                $('<input type="checkbox" name="can_update_tickets" value="1">')
                                    .prop('checked', currentValue == '1')
                            )
                            .append(' Can Update Tickets <em>(PATCH/PUT /tickets/:id)</em>')
                    )
            );

        // Insert after "Can Create Tickets" row
        $createRow.after($newRow);

        console.log('API Endpoints Plugin: Added "Can Update Tickets" checkbox');
    });
})();
