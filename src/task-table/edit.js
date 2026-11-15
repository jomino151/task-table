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
    const richTextFormats = ['core/bold', 'core/italic', 'core/link'];
    const editableFields = [
        { key: 'wasAngefragt', label: __('What is requested?', 'task-table'), placeholder: __('What is requested?', 'task-table') },
        { key: 'kontext', label: __('Context', 'task-table'), placeholder: __('Context', 'task-table') },
        { key: 'nameHochschule', label: __('University name', 'task-table'), placeholder: __('University name', 'task-table') },
        { key: 'tnAnzahl', label: __('Participants', 'task-table'), placeholder: __('Participants', 'task-table') },
        { key: 'zeitpunkt', label: __('Planned date', 'task-table'), placeholder: __('Planned date', 'task-table') },
        { key: 'auftragdetails', label: __('Request details', 'task-table'), placeholder: __('Request details', 'task-table'), isWide: true },
    ];

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
                setCategoryError(__('Could not load categories.', 'task-table'));
                // eslint-disable-next-line no-console
                console.error('Task Table: could not load categories', error);
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
            console.error('Task Table: could not load events:', error);
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
        if (window.confirm(__('Do you really want to delete this event?', 'task-table'))) {
            const rowToDelete = rows[index];
            const rowId = rowToDelete.id;
            const newRows = rows.filter((_, i) => i !== index);
            setAttributes({ rows: newRows });
            if (rowToDelete._pending) return;
            try { await apiFetch({ path: `/task-table/v1/events/${rowId}`, method: 'DELETE' }); } catch (error) { console.error('Task Table: could not delete the row from the database:', error); }
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
                <PanelBody title={ __('Category', 'task-table') } initialOpen={ true }>
                    {isLoadingCategories ? (
                        <Spinner />
                    ) : categories.length > 0 ? (
                        <SelectControl
                            label={ __('Task category', 'task-table') }
                            value={ categoryId ? String(categoryId) : '' }
                            options={ [
                                { label: __('Select category', 'task-table'), value: '' },
                                ...categories.map((cat) => ({ label: cat.name, value: String(cat.id) }))
                            ] }
                            onChange={ handleCategoryChange }
                        />
                    ) : (
                        <Notice status="warning" isDismissible={ false }>
                            { __('No categories available. Create one in the Task Table admin area.', 'task-table') }
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
                    <div style={{ padding: '20px', textAlign: 'center' }}>{ __('Loading events…', 'task-table') }</div>
                )}

                {!isLoadingCategories && categories.length === 0 && (
                    <Notice status="warning" isDismissible={ false }>
                        { __('No categories defined yet. Go to Task Table → Categories to create one.', 'task-table') }
                    </Notice>
                )}

                {categories.length > 0 && !categoryId && !isLoadingCategories && (
                    <Notice status="info" isDismissible={ false }>
                        { __('Choose a category in the block settings to edit its tasks.', 'task-table') }
                    </Notice>
                )}

                {categoryId ? (
                    <>
                        <div className="task-table-active-category">
                            {activeCategory ? (
                                <strong>{sprintf(__('Active category: %s', 'task-table'), activeCategory.name)}</strong>
                            ) : (
                                <strong>{__('Select category', 'task-table')}</strong>
                            )}
                            {categories.length > 0 && (
                                <div className="task-table-inline-category-select">
                                    <SelectControl
                                        label={ __('Displayed category', 'task-table') }
                                        value={ categoryId ? String(categoryId) : '' }
                                        options={ [
                                            { label: __('Select category', 'task-table'), value: '' },
                                            ...categories.map((cat) => ({ label: cat.name, value: String(cat.id) })),
                                        ] }
                                        onChange={ handleCategoryChange }
                                        __nextHasNoMarginBottom
                                    />
                                </div>
                            )}
                        </div>

                        {rows.length === 0 && !isLoadingEvents && (
                            <Notice status="info" isDismissible={ false }>
                                { __('No events in this category yet. Use "Add row" to add the first one.', 'task-table') }
                            </Notice>
                        )}

                        <div className="task-table-editor-list">
                            {rows.map((row, index) => (
                                <section key={row.id || `row-${index}`} className={`task-table-editor-card ${row._pending ? 'pending-row' : ''}`}>
                                    <div className="task-table-editor-card-header">
                                        <strong>{sprintf(__('Event %d', 'task-table'), index + 1)}</strong>
                                        <div className="task-table-editor-card-actions">
                                            {row._pending && (
                                                <span className="task-table-editor-saving">{ __('Saving…', 'task-table') }</span>
                                            )}
                                            <Button
                                                isDestructive
                                                onClick={() => deleteRow(index)}
                                                variant="tertiary"
                                                iconSize={16}
                                                icon="trash"
                                                title={ __('Delete row', 'task-table') }
                                            />
                                        </div>
                                    </div>

                                    <div className="task-table-editor-fields">
                                        {editableFields.map((field) => (
                                            <div key={field.key} className={`task-table-editor-field ${field.isWide ? 'is-wide' : ''}`}>
                                                <label>{field.label}</label>
                                                <RichText
                                                    className="task-table-editor-richtext"
                                                    value={row[field.key] || ''}
                                                    onChange={(value) => updateRow(index, field.key, value)}
                                                    placeholder={field.placeholder}
                                                    allowedFormats={richTextFormats}
                                                />
                                            </div>
                                        ))}
                                    </div>

                                    <div className="task-table-editor-signup-preview">
                                        <span className="task-table-editor-signup-label">{ __('Who\'s doing it? (preview)', 'task-table') }</span>
                                        <div className="task-table-editor-signup-content">
                                            <span>{row.werMacht || __('No approved signups yet.', 'task-table')}</span>
                                            <Button disabled variant="secondary">{ __('Sign up (preview)', 'task-table') }</Button>
                                        </div>
                                    </div>
                                </section>
                            ))}
                        </div>

                        <Button onClick={addRow} variant="primary" className="add-row-button" disabled={!categoryId}>
                            { __('Add row', 'task-table') }
                        </Button>
                    </>
                ) : null}
            </div>
        </>
    );
};

export default Edit;