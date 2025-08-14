import { useBlockProps, RichText } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';
import { useEffect, useState, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const Edit = ({ attributes, setAttributes }) => {
    const { rows = [] } = attributes;
    const [previousRows, setPreviousRows] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const rowUpdateTimers = useRef({});

    // First-time setup - fetch data from database
    useEffect(() => {
        fetchEvents();
    }, []);
    
    // Fetch events from database
    const fetchEvents = async () => {
        setIsLoading(true);
        try {
            const events = await apiFetch({ path: '/task-table/v1/events', method: 'GET' });
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
        } catch (error) {
            console.error('Task Table: Failed to fetch events:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const generateEventId = () => 0;

    // Add row - saves to database
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
            _pending: true
        };
        const newRows = [...rows, newRow];
        setAttributes({ rows: newRows });
        try {
            const savedEvent = await apiFetch({ path: '/task-table/v1/events', method: 'POST', data: newRow });
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
        } catch (error) {
            console.error('Task Table: Failed to save row to database:', error);
        }
    };

    // Update row with debounce
    const updateRow = async (index, field, value) => {
        const newRows = [...rows];
        newRows[index] = { ...newRows[index], [field]: value };
        setAttributes({ rows: newRows });
        if (newRows[index]._pending) return;
        if (rowUpdateTimers.current[index]) clearTimeout(rowUpdateTimers.current[index]);
        rowUpdateTimers.current[index] = setTimeout(async () => {
            try {
                const fieldMap = { wasAngefragt: 'was_angefragt', kontext: 'kontext', nameHochschule: 'name_hochschule', tnAnzahl: 'tn_anzahl', zeitpunkt: 'zeitpunkt', auftragdetails: 'auftragdetails', werMacht: 'wer_macht' };
                const completeRow = { ...newRows[index] };
                const apiData = {};
                for (const [key, v] of Object.entries(completeRow)) {
                    if (key.startsWith('_') || key === 'id') continue;
                    const dbField = fieldMap[key] || key;
                    apiData[dbField] = v;
                }
                const response = await apiFetch({ path: `/task-table/v1/events/${newRows[index].id}`, method: 'PUT', data: apiData });
                if (response.event) {
                    const updatedRow = { id: response.event.id, wasAngefragt: response.event.was_angefragt || '', kontext: response.event.kontext || '', nameHochschule: response.event.name_hochschule || '', tnAnzahl: response.event.tn_anzahl || '', zeitpunkt: response.event.zeitpunkt || '', auftragdetails: response.event.auftragdetails || '', werMacht: response.event.wer_macht || '' };
                    const refreshedRows = [...rows];
                    refreshedRows[index] = updatedRow;
                    setAttributes({ rows: refreshedRows });
                }
            } catch (error) { console.error('Task Table: Failed to update row in database:', error); }
        }, 1000);
    };

    // Delete row
    const deleteRow = async (index) => {
        if (window.confirm('Are you sure you want to delete this event?')) {
            const rowToDelete = rows[index];
            const rowId = rowToDelete.id;
            const newRows = rows.filter((_, i) => i !== index);
            setAttributes({ rows: newRows });
            if (rowToDelete._pending) return;
            try { await apiFetch({ path: `/task-table/v1/events/${rowId}`, method: 'DELETE' }); } catch (error) { console.error('Task Table: Failed to delete row from database:', error); }
        }
    };

    return (
        <div { ...useBlockProps() }>
            {isLoading && (<div style={{ padding: '20px', textAlign: 'center' }}>Lade Veranstaltungen...</div>)}
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
                            <td><RichText value={row.wasAngefragt} onChange={(value) => updateRow(index, 'wasAngefragt', value)} placeholder="Was ist angefragt?" /></td>
                            <td><RichText value={row.kontext} onChange={(value) => updateRow(index, 'kontext', value)} placeholder="Kontext" /></td>
                            <td><RichText value={row.nameHochschule} onChange={(value) => updateRow(index, 'nameHochschule', value)} placeholder="Name der Hochschule" /></td>
                            <td><RichText value={row.tnAnzahl} onChange={(value) => updateRow(index, 'tnAnzahl', value)} placeholder="Vorauss. TN-Anzahl" /></td>
                            <td><RichText value={row.zeitpunkt} onChange={(value) => updateRow(index, 'zeitpunkt', value)} placeholder="Anvisierter Zeitpunkt" /></td>
                            <td><RichText value={row.auftragdetails} onChange={(value) => updateRow(index, 'auftragdetails', value)} placeholder="Auftragdetails" /></td>
                            <td>
                                <div>{row.werMacht}</div>
                                <div className="button-row">
                                    <Button disabled variant="secondary">Anmelden (Vorschau)</Button>
                                </div>
                            </td>
                            <td>
                                <Button isDestructive onClick={() => deleteRow(index)} variant="tertiary" iconSize={16} icon="trash" title="Delete row" />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <Button onClick={addRow} variant="primary" className="add-row-button">Zeile hinzufügen</Button>
            <style>{`.pending-row { background-color: rgba(255, 255, 0, 0.1); }`}</style>
        </div>
    );
};

export default Edit;