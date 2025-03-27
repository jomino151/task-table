import { useBlockProps, RichText } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';
import { useEffect, useState, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const Edit = ({ attributes, setAttributes }) => {
    const { rows = [] } = attributes;
    const [previousRows, setPreviousRows] = useState([]);
    const [notificationSent, setNotificationSent] = useState(false);
    const [notificationError, setNotificationError] = useState('');
    const [debugInfo, setDebugInfo] = useState(null);
    const [initialLoad, setInitialLoad] = useState(true);
    const [notificationQueue, setNotificationQueue] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const rowUpdateTimers = useRef({});

    // First-time setup - fetch data from database
    useEffect(() => {
        // Only run on first render
        if (initialLoad) {
            // Ensure WordPress REST API is initialized
            if (typeof wp !== 'undefined' && wp.api && !window.wpApiSettings) {
                console.log('Task Table: Initializing REST API settings');
                window.wpApiSettings = {
                    nonce: wp.api.utils.nonce || '',
                    root: wp.api.utils.root || '/wp-json'
                };
            }
            
            // Load events from database
            fetchEvents();
            
            setInitialLoad(false);
            console.log('Task Table: Initial data fetch initiated');
        }
    }, []);
    
    // Fetch events from database
    const fetchEvents = async () => {
        setIsLoading(true);
        
        try {
            const events = await apiFetch({
                path: '/task-table/v1/events',
                method: 'GET'
            });
            
            // Convert database format to frontend format
            const formattedEvents = events.map(event => ({
                id: event.id,
                wasAngefragt: event.was_angefragt,
                kontext: event.kontext,
                nameHochschule: event.name_hochschule,
                tnAnzahl: event.tn_anzahl,
                zeitpunkt: event.zeitpunkt,
                auftragdetails: event.auftragdetails,
                werMacht: event.wer_macht
            }));
            
            setAttributes({ rows: formattedEvents });
            setPreviousRows(JSON.parse(JSON.stringify(formattedEvents)));
            console.log('Task Table: Loaded', formattedEvents.length, 'events from database');
        } catch (error) {
            console.error('Task Table: Failed to fetch events:', error);
            setNotificationError('Failed to load events from database');
            setTimeout(() => setNotificationError(''), 8000);
        } finally {
            setIsLoading(false);
        }
    };

    // Process any rows in the notification queue - only for manual test button clicks
    useEffect(() => {
        if (notificationQueue.length > 0) {
            const row = notificationQueue[0];
            console.log('Task Table: Processing test notification for row:', row);
            
            triggerNewEventNotification(row)
                .then(result => {
                    // Store complete response for debugging
                    setDebugInfo(result);
                    console.log('Task Table: Notification response:', result);
                    
                    if (result.success) {
                        if (result.notification_sent) {
                            setNotificationSent(true);
                            setTimeout(() => setNotificationSent(false), 5000);
                        } else {
                            setNotificationError(`Test erfolgreich, aber keine E-Mails gesendet: ${result.message || 'Keine Abonnenten'}`);
                            setTimeout(() => setNotificationError(''), 8000);
                        }
                    } else {
                        setNotificationError(result.message || 'Fehler beim Testen der Benachrichtigung');
                        setTimeout(() => setNotificationError(''), 8000);
                    }
                    
                    // Remove the processed item from queue
                    setNotificationQueue(current => current.slice(1));
                })
                .catch(error => {
                    console.error('Task Table: API error:', error);
                    setNotificationError('API-Fehler: ' + (error.message || 'Unbekannter Fehler'));
                    setDebugInfo({error: error.toString(), stack: error.stack});
                    setTimeout(() => setNotificationError(''), 8000);
                    
                    // Remove the failed item from queue
                    setNotificationQueue(current => current.slice(1));
                });
        }
    }, [notificationQueue]);

    // Function to trigger event notification with improved error handling and retry
    const triggerNewEventNotification = async (row, retries = 2) => {
        try {
            console.log('Task Table: Sending test notification API request for:', row);
            
            // Ensure we have the WordPress API settings
            const nonce = window.wpApiSettings?.nonce || '';
            
            // FIXED: Use path without wp-json prefix since apiFetch automatically adds it
            const response = await apiFetch({
                path: '/task-table/v1/trigger-event-notification',
                method: 'POST',
                data: { 
                    event: row, 
                    _trigger_source: 'block_editor_test_button',  // Changed to indicate this is a manual test
                    _test_mode: true  // Add flag to indicate this is just a test
                },
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                }
            });
            
            console.log('Task Table: Test notification API response:', response);
            return {
                success: response.success,
                message: response.message,
                notification_sent: response.notification_sent,
                subscriber_count: response.subscriber_count || 0,
                response: response
            };
        } catch (e) {
            console.error('Task Table: Failed to trigger test notification:', e);
            
            // If this is a "rest_no_route" error, try sending directly to the server
            if (e.code === 'rest_no_route' && retries > 0) {
                console.log('Task Table: REST route not found, trying direct server notification');
                
                // Try direct server notification as fallback
                try {
                    const formData = new FormData();
                    formData.append('action', 'task_table_direct_notification');
                    formData.append('event_data', JSON.stringify(row));
                    formData.append('test_mode', '1');  // Add test mode flag
                    formData.append('security', nonce);
                    
                    // FIXED: Use admin-ajax.php from taskTableSettings or fallback
                    const ajaxUrl = window.taskTableSettings?.ajaxurl || '/wp-admin/admin-ajax.php';
                    console.log('Task Table: Sending to admin-ajax URL:', ajaxUrl);
                    
                    const ajaxResponse = await fetch(ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    });
                    
                    if (ajaxResponse.ok) {
                        const result = await ajaxResponse.json();
                        console.log('Task Table: Admin-ajax test notification response:', result);
                        return {
                            success: true,
                            message: 'Test notification sent via admin-ajax fallback',
                            notification_sent: result.data && result.data.sent,
                            fallback: true
                        };
                    } else {
                        throw new Error('Admin-ajax response not OK: ' + ajaxResponse.status);
                    }
                } catch (ajaxError) {
                    console.error('Task Table: Fallback also failed:', ajaxError);
                }
            }
            
            // Regular retry logic
            if (retries > 0) {
                console.log(`Task Table: Retrying API call... (${retries} attempts left)`);
                await new Promise(resolve => setTimeout(resolve, 1000));
                return triggerNewEventNotification(row, retries - 1);
            }
            
            return {
                success: false,
                message: e.message || 'Ein Fehler ist aufgetreten',
                error: e
            };
        }
    };

    // Test button function - purely for testing, doesn't send real notifications
    const testNotification = (row) => {
        setNotificationQueue(current => [...current, row]);
    };

    const generateEventId = () => {
        // No longer need to generate IDs - database will handle this
        return 0; // Temp ID for frontend only, will be replaced by database
    };

    // Add row - now saves to database
    const addRow = async () => {
        const newRow = { 
            id: generateEventId(), 
            wasAngefragt: '', 
            kontext: '', 
            nameHochschule: '', 
            tnAnzahl: '', 
            zeitpunkt: '', 
            auftragdetails: '',
            werMacht: '',
            _pending: true  // Flag to indicate this row is new and pending save
        };
        
        // Add to UI immediately for better UX
        const newRows = [...rows, newRow];
        setAttributes({ rows: newRows });
        
        try {
            // Save to database
            const savedEvent = await apiFetch({
                path: '/task-table/v1/events',
                method: 'POST',
                data: newRow
            });
            
            // Update the row with the database ID and remove pending flag
            const updatedRows = newRows.map((row, index) => {
                if (index === newRows.length - 1) {
                    return {
                        id: savedEvent.event.id,
                        wasAngefragt: savedEvent.event.was_angefragt,
                        kontext: savedEvent.event.kontext,
                        nameHochschule: savedEvent.event.name_hochschule,
                        tnAnzahl: savedEvent.event.tn_anzahl,
                        zeitpunkt: savedEvent.event.zeitpunkt,
                        auftragdetails: savedEvent.event.auftragdetails,
                        werMacht: savedEvent.event.wer_macht,
                    };
                }
                return row;
            });
            
            setAttributes({ rows: updatedRows });
            console.log('Task Table: Row saved to database with ID:', savedEvent.event.id);
        } catch (error) {
            console.error('Task Table: Failed to save row to database:', error);
            setNotificationError('Failed to save row to database: ' + (error.message || 'Unknown error'));
            setTimeout(() => setNotificationError(''), 8000);
        }
    };

    // Update row - completely rewritten for reliability
    const updateRow = async (index, field, value) => {
        // Update local state immediately for responsive UI
        const newRows = [...rows];
        newRows[index] = { ...newRows[index], [field]: value };
        setAttributes({ rows: newRows });
        
        // Don't update database for pending rows
        if (newRows[index]._pending) {
            return;
        }
        
        // Clear existing timer for this row
        if (rowUpdateTimers.current[index]) {
            clearTimeout(rowUpdateTimers.current[index]);
        }
        
        // Set debounce timer
        rowUpdateTimers.current[index] = setTimeout(async () => {
            try {
                console.log(`Task Table: Saving update for row ${index}, field ${field}, value:`, value);
                
                // Map frontend field names to database field names
                const fieldMap = {
                    wasAngefragt: 'was_angefragt',
                    kontext: 'kontext',
                    nameHochschule: 'name_hochschule',
                    tnAnzahl: 'tn_anzahl',
                    zeitpunkt: 'zeitpunkt',
                    auftragdetails: 'auftragdetails',
                    werMacht: 'wer_macht'
                };
                
                // Get the complete row data
                const completeRow = { ...newRows[index] };
                
                // Convert frontend field names to database field names
                const apiData = {};
                for (const [key, value] of Object.entries(completeRow)) {
                    // Skip special properties that start with underscore
                    if (key.startsWith('_') || key === 'id') continue;
                    
                    // Use the mapped field name if available
                    const dbField = fieldMap[key] || key;
                    apiData[dbField] = value;
                }
                
                // Make the API call with all row data
                const response = await apiFetch({
                    path: `/task-table/v1/events/${newRows[index].id}`,
                    method: 'PUT',
                    data: apiData
                });
                
                if (!response.success) {
                    throw new Error(response.message || 'Update failed');
                }
                
                console.log(`Task Table: Successfully updated row ${index}`, response);
                
                // Update the row with fresh data from the server to ensure consistency
                if (response.event) {
                    const updatedRow = {
                        id: response.event.id,
                        wasAngefragt: response.event.was_angefragt || '',
                        kontext: response.event.kontext || '',
                        nameHochschule: response.event.name_hochschule || '',
                        tnAnzahl: response.event.tn_anzahl || '',
                        zeitpunkt: response.event.zeitpunkt || '',
                        auftragdetails: response.event.auftragdetails || '',
                        werMacht: response.event.wer_macht || ''
                    };
                    
                    // Update the rows array with the fresh data
                    const refreshedRows = [...rows];
                    refreshedRows[index] = updatedRow;
                    setAttributes({ rows: refreshedRows });
                }
            } catch (error) {
                console.error('Task Table: Failed to update row in database:', error);
                
                // Show error notification
                setNotificationError('Failed to update row in database: ' + (error.message || 'Unknown error'));
                setTimeout(() => setNotificationError(''), 8000);
                
                // Set debug info
                setDebugInfo({
                    error: error.toString(),
                    context: 'row update',
                    rowIndex: index,
                    field: field,
                    time: new Date().toISOString()
                });
            }
        }, 1000); // 1 second debounce
    };

    // Delete row
    const deleteRow = async (index) => {
        if (window.confirm('Are you sure you want to delete this event?')) {
            const rowToDelete = rows[index];
            const rowId = rowToDelete.id;
            
            // Remove from UI immediately for better UX
            const newRows = rows.filter((_, i) => i !== index);
            setAttributes({ rows: newRows });
            
            // Don't try to delete from database if it's a pending row
            if (rowToDelete._pending) {
                return;
            }
            
            try {
                await apiFetch({
                    path: `/task-table/v1/events/${rowId}`,
                    method: 'DELETE'
                });
                
                console.log('Task Table: Deleted event with ID:', rowId);
            } catch (error) {
                console.error('Task Table: Failed to delete row from database:', error);
                
                // Put the row back on error
                setAttributes({ rows: [...rows] });
                
                setNotificationError('Failed to delete row from database: ' + (error.message || 'Unknown error'));
                setTimeout(() => setNotificationError(''), 8000);
            }
        }
    };

    return (
        <div { ...useBlockProps() }>
            {isLoading && (
                <div style={{ padding: '20px', textAlign: 'center' }}>
                    Lade Veranstaltungen...
                </div>
            )}
            
            {notificationSent && (
                <div className="notification-feedback success" style={{
                    backgroundColor: '#d4edda',
                    color: '#155724',
                    padding: '10px',
                    marginBottom: '15px',
                    borderRadius: '4px'
                }}>
                    Test-Benachrichtigung erfolgreich durchgeführt!
                </div>
            )}
            
            {notificationError && (
                <div className="notification-feedback error" style={{
                    backgroundColor: '#f8d7da',
                    color: '#721c24',
                    padding: '10px',
                    marginBottom: '15px',
                    borderRadius: '4px'
                }}>
                    {notificationError}
                </div>
            )}
            
            {debugInfo && (typeof WP_DEBUG !== 'undefined' && WP_DEBUG || true) && (
                <div className="debug-info" style={{
                    backgroundColor: '#f0f8ff',
                    border: '1px solid #ccc',
                    padding: '10px',
                    marginBottom: '15px',
                    fontSize: '12px',
                    maxHeight: '150px',
                    overflow: 'auto'
                }}>
                    <strong>Debug Info:</strong>
                    <pre>{JSON.stringify(debugInfo, null, 2)}</pre>
                </div>
            )}
            
            <table className="event-table-editor">
                <thead>
                    <tr>
                        <th>Was ist angefragt?</th>
                        <th>Kontext</th>
                        <th>Name der Hochschule</th>
                        <th>Vorauss. TN-Anzahl</th>
                        <th>Anvisierter Zeitpunkt</th>
                        <th>Auftragdetails</th>
                        <th>Wer macht's?</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr key={index} className={row._pending ? 'pending-row' : ''}>
                            <td>
                                <RichText
                                    value={row.wasAngefragt}
                                    onChange={(value) => updateRow(index, 'wasAngefragt', value)}
                                    placeholder="Was ist angefragt?"
                                />
                            </td>
                            <td>
                                <RichText
                                    value={row.kontext}
                                    onChange={(value) => updateRow(index, 'kontext', value)}
                                    placeholder="Kontext"
                                />
                            </td>
                            <td>
                                <RichText
                                    value={row.nameHochschule}
                                    onChange={(value) => updateRow(index, 'nameHochschule', value)}
                                    placeholder="Name der Hochschule"
                                />
                            </td>
                            <td>
                                <RichText
                                    value={row.tnAnzahl}
                                    onChange={(value) => updateRow(index, 'tnAnzahl', value)}
                                    placeholder="Vorauss. TN-Anzahl"
                                />
                            </td>
                            <td>
                                <RichText
                                    value={row.zeitpunkt}
                                    onChange={(value) => updateRow(index, 'zeitpunkt', value)}
                                    placeholder="Anvisierter Zeitpunkt"
                                />
                            </td>
                            <td>
                                <RichText
                                    value={row.auftragdetails}
                                    onChange={(value) => updateRow(index, 'auftragdetails', value)}
                                    placeholder="Auftragdetails"
                                />
                            </td>
                            <td>
                                {/* Read-only field for "Wer macht's?" */}
                                <div>{row.werMacht}</div>
                                {/* Button row with notification trigger option */}
                                <div className="button-row">
                                    <Button disabled variant="secondary">
                                        Anmelden (Vorschau)
                                    </Button>
                                    
                                    {/* Clear test button with better label */}
                                    {(row.wasAngefragt || row.kontext) && (
                                        <Button 
                                            variant="secondary"
                                            onClick={() => testNotification(row)}
                                            style={{marginTop: '5px', fontSize: '10px'}}
                                        >
                                            Nur Test (keine echte E-Mail)
                                        </Button>
                                    )}
                                </div>
                            </td>
                            <td>
                                <Button 
                                    isDestructive
                                    onClick={() => deleteRow(index)}
                                    variant="tertiary"
                                    iconSize={16}
                                    icon="trash"
                                    title="Delete row"
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            
            <Button onClick={addRow} variant="primary" className="add-row-button">
                Zeile hinzufügen
            </Button>
            
            <style>
                {`
                .pending-row {
                    background-color: rgba(255, 255, 0, 0.1);
                }
                `}
            </style>
        </div>
    );
};

export default Edit;