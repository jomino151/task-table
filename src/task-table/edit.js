import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { Button, PanelBody, SelectControl, Spinner, Notice } from '@wordpress/components';
import { useEffect, useState, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const Edit = ({ attributes, setAttributes }) => {
    const { rows = [], categoryId = 0 } = attributes;
    const [categories, setCategories] = useState([]);
    const [isLoadingCategories, setIsLoadingCategories] = useState(true);
    const [categoryError, setCategoryError] = useState('');
    const [isLoadingEvents, setIsLoadingEvents] = useState(false);
    const rowUpdateTimers = useRef({});
    const latestFetchCategory = useRef(null);

    const activeCategory = categories.find((cat) => cat.id === categoryId);

    useEffect(() => () => {
        Object.values(rowUpdateTimers.current).forEach((timeout) => clearTimeout(timeout));
    }, []);

    useEffect(() => {
        Object.values(rowUpdateTimers.current).forEach((timeout) => clearTimeout(timeout));
        rowUpdateTimers.current = {};
    }, [categoryId]);

    useEffect(() => {
        const loadCategories = async () => {
            setIsLoadingCategories(true);
            setCategoryError('');
            try {
                const fetched = await apiFetch({ path: '/task-table/v1/categories', method: 'GET' });
                const normalized = Array.isArray(fetched)
                    ? fetched.map((cat) => ({
                        id: parseInt(cat.id, 10),
                        name: cat.name,
                        slug: cat.slug,
                        is_default: !!cat.is_default,
                    }))
                    : [];

                setCategories(normalized);

                if (normalized.length === 0) {
                    setAttributes({ categoryId: 0, rows: [] });
                    return;
                }

                const hasCurrent = normalized.some((cat) => cat.id === categoryId);
                if (!hasCurrent) {
                    const fallback = normalized.find((cat) => cat.is_default) || normalized[0];
                    if (fallback) {
                        setAttributes({ categoryId: fallback.id });
                    }
                }
            } catch (error) {
                setCategoryError(__('Kategorien konnten nicht geladen werden.', 'task-table'));
                // eslint-disable-next-line no-console
                console.error('Task Table: Kategorien konnten nicht geladen werden', error);
            } finally {
                setIsLoadingCategories(false);
            }
        };

        loadCategories();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!categoryId) {
            setIsLoadingEvents(false);
            return;
        }
        fetchEvents(categoryId);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [categoryId]);

    const fetchEvents = async (currentCategoryId) => {
        if (!currentCategoryId) {
            return;
        }
        latestFetchCategory.current = currentCategoryId;
        setIsLoadingEvents(true);
        try {
                const events = await apiFetch({ path: `/task-table/v1/events?category_id=${currentCategoryId}`, method: 'GET' });
            const formattedEvents = (events || []).map((event) => ({
                id: event.id,
                wasAngefragt: event.was_angefragt || '',
                kontext: event.kontext || '',
                nameHochschule: event.name_hochschule || '',
                tnAnzahl: event.tn_anzahl || '',
                zeitpunkt: event.zeitpunkt || '',
                auftragdetails: event.auftragdetails || '',
                werMacht: event.wer_macht || '',
                categoryId: event.category_id ? parseInt(event.category_id, 10) : currentCategoryId,
            }));
            if (latestFetchCategory.current === currentCategoryId) {
                setAttributes({ rows: formattedEvents });
            }
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error('Task Table: Veranstaltungen konnten nicht geladen werden:', error);
        } finally {
            if (latestFetchCategory.current === currentCategoryId) {
                setIsLoadingEvents(false);
            }
        }
    };
    // Add row - saves to database
    const addRow = async () => {
        if (!categoryId) {
            return;
        }
        const newRow = { 
            id: 0, 
            wasAngefragt: '', 
            kontext: '', 
            nameHochschule: '', 
            tnAnzahl: '', 
            zeitpunkt: '', 
            auftragdetails: '',
            werMacht: '',
            categoryId,
            _pending: true
        };
        const newRows = [...rows, newRow];
        setAttributes({ rows: newRows });
        try {
            const savedEvent = await apiFetch({ path: '/task-table/v1/events', method: 'POST', data: { ...newRow, category_id: categoryId } });
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
                        categoryId: savedEvent.event.category_id ? parseInt(savedEvent.event.category_id, 10) : categoryId,
                    };
                }
                return row;
            });
            setAttributes({ rows: updatedRows });
        } catch (error) {
            console.error('Task Table: Zeile konnte nicht in der Datenbank gespeichert werden:', error);
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
                const fieldMap = { wasAngefragt: 'was_angefragt', kontext: 'kontext', nameHochschule: 'name_hochschule', tnAnzahl: 'tn_anzahl', zeitpunkt: 'zeitpunkt', auftragdetails: 'auftragdetails', werMacht: 'wer_macht', categoryId: 'category_id' };
                const completeRow = { ...newRows[index] };
                const apiData = {};
                for (const [key, v] of Object.entries(completeRow)) {
                    if (key.startsWith('_') || key === 'id') continue;
                    const dbField = fieldMap[key] || key;
                    apiData[dbField] = v;
                }
                apiData.category_id = apiData.category_id ? parseInt(apiData.category_id, 10) : categoryId;
                const response = await apiFetch({ path: `/task-table/v1/events/${newRows[index].id}`, method: 'PUT', data: apiData });
                if (response.event) {
                    const updatedRow = { id: response.event.id, wasAngefragt: response.event.was_angefragt || '', kontext: response.event.kontext || '', nameHochschule: response.event.name_hochschule || '', tnAnzahl: response.event.tn_anzahl || '', zeitpunkt: response.event.zeitpunkt || '', auftragdetails: response.event.auftragdetails || '', werMacht: response.event.wer_macht || '' };
                    updatedRow.categoryId = response.event.category_id ? parseInt(response.event.category_id, 10) : categoryId;
                    const refreshedRows = [...rows];
                    refreshedRows[index] = updatedRow;
                    setAttributes({ rows: refreshedRows });
                }
            } catch (error) { console.error('Task Table: Zeile konnte nicht in der Datenbank aktualisiert werden:', error); }
        }, 1000);
    };

    // Delete row
    const deleteRow = async (index) => {
        if (window.confirm(__('Möchtest du dieses Ereignis wirklich löschen?', 'task-table'))) {
            const rowToDelete = rows[index];
            const rowId = rowToDelete.id;
            const newRows = rows.filter((_, i) => i !== index);
            setAttributes({ rows: newRows });
            if (rowToDelete._pending) return;
            try { await apiFetch({ path: `/task-table/v1/events/${rowId}`, method: 'DELETE' }); } catch (error) { console.error('Task Table: Zeile konnte nicht aus der Datenbank gelöscht werden:', error); }
        }
    };

    const handleCategoryChange = (value) => {
        const nextCategoryId = parseInt(value, 10) || 0;

        Object.values(rowUpdateTimers.current).forEach((timeout) => clearTimeout(timeout));
        rowUpdateTimers.current = {};

        if (nextCategoryId === categoryId) {
            return;
        }

        if (!nextCategoryId) {
            setAttributes({ categoryId: 0, rows: [] });
            return;
        }

        setAttributes({ categoryId: nextCategoryId, rows: [] });
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __('Kategorie', 'task-table') } initialOpen={ true }>
                    {isLoadingCategories ? (
                        <Spinner />
                    ) : categories.length > 0 ? (
                        <SelectControl
                            label={ __('Aufgabenkategorie', 'task-table') }
                            value={ categoryId ? String(categoryId) : '' }
                            options={ [
                                { label: __('Kategorie auswählen', 'task-table'), value: '' },
                                ...categories.map((cat) => ({ label: cat.name, value: String(cat.id) }))
                            ] }
                            onChange={ handleCategoryChange }
                        />
                    ) : (
                        <Notice status="warning" isDismissible={ false }>
                            { __('Keine Kategorien verfügbar. Lege eine im Task-Table-Adminbereich an.', 'task-table') }
                        </Notice>
                    )}
                    {categoryError && (
                        <Notice status="error" isDismissible={ false }>
                            { categoryError }
                        </Notice>
                    )}
                </PanelBody>
            </InspectorControls>
            <div { ...useBlockProps() }>
                {isLoadingEvents && (
                    <div style={{ padding: '20px', textAlign: 'center' }}>{ __('Veranstaltungen werden geladen …', 'task-table') }</div>
                )}

                {!isLoadingCategories && categories.length === 0 && (
                    <Notice status="warning" isDismissible={ false }>
                        { __('Noch keine Kategorien definiert. Besuche Task Table → Kategorien, um eine anzulegen.', 'task-table') }
                    </Notice>
                )}

                {categories.length > 0 && !categoryId && !isLoadingCategories && (
                    <Notice status="info" isDismissible={ false }>
                        { __('Wähle in den Blockeinstellungen eine Kategorie, um deren Aufgaben zu bearbeiten.', 'task-table') }
                    </Notice>
                )}

                {categoryId ? (
                    <>
                        <div className="task-table-active-category">
                            {activeCategory ? (
                                <strong>{sprintf(__('Aktive Kategorie: %s', 'task-table'), activeCategory.name)}</strong>
                            ) : (
                                <strong>{__('Kategorie auswählen', 'task-table')}</strong>
                            )}
                            {categories.length > 0 && (
                                <div className="task-table-inline-category-select">
                                    <SelectControl
                                        label={ __('Angezeigte Kategorie', 'task-table') }
                                        value={ categoryId ? String(categoryId) : '' }
                                        options={ [
                                            { label: __('Kategorie auswählen', 'task-table'), value: '' },
                                            ...categories.map((cat) => ({ label: cat.name, value: String(cat.id) })),
                                        ] }
                                        onChange={ handleCategoryChange }
                                        __nextHasNoMarginBottom
                                    />
                                </div>
                            )}
                        </div>
                        <p className="task-table-format-hint">
                            {__('Formatierungstipp: In allen Textfeldern sind Fett/Kursiv und Links uber die Rich-Text-Werkzeugleiste moglich.', 'task-table')}
                        </p>
                        <table className="event-table-editor">
                            <thead>
                                <tr>
                                    <th>{ __('Was ist angefragt?', 'task-table') }</th>
                                    <th>{ __('Kontext', 'task-table') }</th>
                                    <th>{ __('Name der Hochschule', 'task-table') }</th>
                                    <th>{ __('Vorauss. TN-Anzahl', 'task-table') }</th>
                                    <th>{ __('Anvisierter Zeitpunkt', 'task-table') }</th>
                                    <th>{ __('Auftragdetails', 'task-table') }</th>
                                    <th>{ __('Wer macht\'s?', 'task-table') }</th>
                                    <th>{ __('Aktionen', 'task-table') }</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, index) => (
                                    <tr key={index} className={row._pending ? 'pending-row' : ''}>
                                        <td><RichText value={row.wasAngefragt} onChange={(value) => updateRow(index, 'wasAngefragt', value)} placeholder={ __('Was ist angefragt?', 'task-table') } /></td>
                                        <td><RichText value={row.kontext} onChange={(value) => updateRow(index, 'kontext', value)} placeholder={ __('Kontext', 'task-table') } /></td>
                                        <td><RichText value={row.nameHochschule} onChange={(value) => updateRow(index, 'nameHochschule', value)} placeholder={ __('Name der Hochschule', 'task-table') } /></td>
                                        <td><RichText value={row.tnAnzahl} onChange={(value) => updateRow(index, 'tnAnzahl', value)} placeholder={ __('Vorauss. TN-Anzahl', 'task-table') } /></td>
                                        <td><RichText value={row.zeitpunkt} onChange={(value) => updateRow(index, 'zeitpunkt', value)} placeholder={ __('Anvisierter Zeitpunkt', 'task-table') } /></td>
                                        <td><RichText value={row.auftragdetails} onChange={(value) => updateRow(index, 'auftragdetails', value)} placeholder={ __('Auftragdetails', 'task-table') } /></td>
                                        <td>
                                            <div>{row.werMacht}</div>
                                            <div className="button-row">
                                                <Button disabled variant="secondary">{ __('Anmelden (Vorschau)', 'task-table') }</Button>
                                            </div>
                                        </td>
                                        <td>
                                            <Button isDestructive onClick={() => deleteRow(index)} variant="tertiary" iconSize={16} icon="trash" title={ __('Zeile löschen', 'task-table') } />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <Button onClick={addRow} variant="primary" className="add-row-button" disabled={!categoryId}>
                            { __('Zeile hinzufügen', 'task-table') }
                        </Button>
                    </>
                ) : null}
                <style>{`
                    .pending-row { background-color: rgba(255, 255, 0, 0.1); }
                    .task-table-active-category { margin-bottom: 16px; display: flex; flex-direction: column; gap: 8px; }
                    .task-table-format-hint { margin: 0 0 12px; color: #1f2933; font-size: 13px; }
                    .task-table-inline-category-select .components-select-control__input { max-width: 260px; }
                `}</style>
            </div>
        </>
    );
};

export default Edit;