/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/task-table/block.json":
/*!***********************************!*\
  !*** ./src/task-table/block.json ***!
  \***********************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"task-table/task-table","version":"0.1.0","title":"Task-Tabelle","category":"text","icon":"table","description":"Erstellt eine dynamische Tabelle zur Kommunikation von Aufgaben.","example":{},"supports":{"html":false},"attributes":{"rows":{"type":"array","default":[]},"categoryId":{"type":"number","default":0}},"textdomain":"task-table","editorScript":"file:./index.js","editorStyle":"file:./index.css","style":"file:./style-index.css","render":"file:./render.php","viewScript":"file:./view.js"}');

/***/ }),

/***/ "./src/task-table/edit.js":
/*!********************************!*\
  !*** ./src/task-table/edit.js ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);






const Edit = ({
  attributes,
  setAttributes
}) => {
  const {
    rows = [],
    categoryId = 0
  } = attributes;
  const [categories, setCategories] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)([]);
  const [isLoadingCategories, setIsLoadingCategories] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(true);
  const [categoryError, setCategoryError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)('');
  const [isLoadingEvents, setIsLoadingEvents] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(false);
  const rowUpdateTimers = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useRef)({});
  const latestFetchCategory = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useRef)(null);
  const activeCategory = categories.find(cat => cat.id === categoryId);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => () => {
    Object.values(rowUpdateTimers.current).forEach(timeout => clearTimeout(timeout));
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    Object.values(rowUpdateTimers.current).forEach(timeout => clearTimeout(timeout));
    rowUpdateTimers.current = {};
  }, [categoryId]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    const loadCategories = async () => {
      setIsLoadingCategories(true);
      setCategoryError('');
      try {
        const fetched = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
          path: '/task-table/v1/categories',
          method: 'GET'
        });
        const normalized = Array.isArray(fetched) ? fetched.map(cat => ({
          id: parseInt(cat.id, 10),
          name: cat.name,
          slug: cat.slug,
          is_default: !!cat.is_default
        })) : [];
        setCategories(normalized);
        if (normalized.length === 0) {
          setAttributes({
            categoryId: 0,
            rows: []
          });
          return;
        }
        const hasCurrent = normalized.some(cat => cat.id === categoryId);
        if (!hasCurrent) {
          const fallback = normalized.find(cat => cat.is_default) || normalized[0];
          if (fallback) {
            setAttributes({
              categoryId: fallback.id
            });
          }
        }
      } catch (error) {
        setCategoryError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Kategorien konnten nicht geladen werden.', 'task-table'));
        // eslint-disable-next-line no-console
        console.error('Task Table: Kategorien konnten nicht geladen werden', error);
      } finally {
        setIsLoadingCategories(false);
      }
    };
    loadCategories();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    if (!categoryId) {
      setIsLoadingEvents(false);
      return;
    }
    fetchEvents(categoryId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [categoryId]);
  const fetchEvents = async currentCategoryId => {
    if (!currentCategoryId) {
      return;
    }
    latestFetchCategory.current = currentCategoryId;
    setIsLoadingEvents(true);
    try {
      const events = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: `/task-table/v1/events?category_id=${currentCategoryId}`,
        method: 'GET'
      });
      const formattedEvents = (events || []).map(event => ({
        id: event.id,
        wasAngefragt: event.was_angefragt || '',
        kontext: event.kontext || '',
        nameHochschule: event.name_hochschule || '',
        tnAnzahl: event.tn_anzahl || '',
        zeitpunkt: event.zeitpunkt || '',
        auftragdetails: event.auftragdetails || '',
        werMacht: event.wer_macht || '',
        categoryId: event.category_id ? parseInt(event.category_id, 10) : currentCategoryId
      }));
      if (latestFetchCategory.current === currentCategoryId) {
        setAttributes({
          rows: formattedEvents
        });
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
    setAttributes({
      rows: newRows
    });
    try {
      const savedEvent = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: '/task-table/v1/events',
        method: 'POST',
        data: {
          ...newRow,
          category_id: categoryId
        }
      });
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
            categoryId: savedEvent.event.category_id ? parseInt(savedEvent.event.category_id, 10) : categoryId
          };
        }
        return row;
      });
      setAttributes({
        rows: updatedRows
      });
    } catch (error) {
      console.error('Task Table: Zeile konnte nicht in der Datenbank gespeichert werden:', error);
    }
  };

  // Update row with debounce
  const updateRow = async (index, field, value) => {
    const newRows = [...rows];
    newRows[index] = {
      ...newRows[index],
      [field]: value
    };
    setAttributes({
      rows: newRows
    });
    if (newRows[index]._pending) return;
    if (rowUpdateTimers.current[index]) clearTimeout(rowUpdateTimers.current[index]);
    rowUpdateTimers.current[index] = setTimeout(async () => {
      try {
        const fieldMap = {
          wasAngefragt: 'was_angefragt',
          kontext: 'kontext',
          nameHochschule: 'name_hochschule',
          tnAnzahl: 'tn_anzahl',
          zeitpunkt: 'zeitpunkt',
          auftragdetails: 'auftragdetails',
          werMacht: 'wer_macht',
          categoryId: 'category_id'
        };
        const completeRow = {
          ...newRows[index]
        };
        const apiData = {};
        for (const [key, v] of Object.entries(completeRow)) {
          if (key.startsWith('_') || key === 'id') continue;
          const dbField = fieldMap[key] || key;
          apiData[dbField] = v;
        }
        apiData.category_id = apiData.category_id ? parseInt(apiData.category_id, 10) : categoryId;
        const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
          path: `/task-table/v1/events/${newRows[index].id}`,
          method: 'PUT',
          data: apiData
        });
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
          updatedRow.categoryId = response.event.category_id ? parseInt(response.event.category_id, 10) : categoryId;
          const refreshedRows = [...rows];
          refreshedRows[index] = updatedRow;
          setAttributes({
            rows: refreshedRows
          });
        }
      } catch (error) {
        console.error('Task Table: Zeile konnte nicht in der Datenbank aktualisiert werden:', error);
      }
    }, 1000);
  };

  // Delete row
  const deleteRow = async index => {
    if (window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Möchtest du dieses Ereignis wirklich löschen?', 'task-table'))) {
      const rowToDelete = rows[index];
      const rowId = rowToDelete.id;
      const newRows = rows.filter((_, i) => i !== index);
      setAttributes({
        rows: newRows
      });
      if (rowToDelete._pending) return;
      try {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
          path: `/task-table/v1/events/${rowId}`,
          method: 'DELETE'
        });
      } catch (error) {
        console.error('Task Table: Zeile konnte nicht aus der Datenbank gelöscht werden:', error);
      }
    }
  };
  const handleCategoryChange = value => {
    const nextCategoryId = parseInt(value, 10) || 0;
    Object.values(rowUpdateTimers.current).forEach(timeout => clearTimeout(timeout));
    rowUpdateTimers.current = {};
    if (nextCategoryId === categoryId) {
      return;
    }
    if (!nextCategoryId) {
      setAttributes({
        categoryId: 0,
        rows: []
      });
      return;
    }
    setAttributes({
      categoryId: nextCategoryId,
      rows: []
    });
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Kategorie', 'task-table'),
        initialOpen: true,
        children: [isLoadingCategories ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Spinner, {}) : categories.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Aufgabenkategorie', 'task-table'),
          value: categoryId ? String(categoryId) : '',
          options: [{
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Kategorie auswählen', 'task-table'),
            value: ''
          }, ...categories.map(cat => ({
            label: cat.name,
            value: String(cat.id)
          }))],
          onChange: handleCategoryChange
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
          status: "warning",
          isDismissible: false,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Keine Kategorien verfügbar. Lege eine im Task-Table-Adminbereich an.', 'task-table')
        }), categoryError && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
          status: "error",
          isDismissible: false,
          children: categoryError
        })]
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
      ...(0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.useBlockProps)(),
      children: [isLoadingEvents && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
        style: {
          padding: '20px',
          textAlign: 'center'
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Veranstaltungen werden geladen …', 'task-table')
      }), !isLoadingCategories && categories.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
        status: "warning",
        isDismissible: false,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Noch keine Kategorien definiert. Besuche Task Table → Kategorien, um eine anzulegen.', 'task-table')
      }), categories.length > 0 && !categoryId && !isLoadingCategories && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
        status: "info",
        isDismissible: false,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Wähle in den Blockeinstellungen eine Kategorie, um deren Aufgaben zu bearbeiten.', 'task-table')
      }), categoryId ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          className: "task-table-active-category",
          children: [activeCategory ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Aktive Kategorie: %s', 'task-table'), activeCategory.name)
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Kategorie auswählen', 'task-table')
          }), categories.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
            className: "task-table-inline-category-select",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Angezeigte Kategorie', 'task-table'),
              value: categoryId ? String(categoryId) : '',
              options: [{
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Kategorie auswählen', 'task-table'),
                value: ''
              }, ...categories.map(cat => ({
                label: cat.name,
                value: String(cat.id)
              }))],
              onChange: handleCategoryChange,
              __nextHasNoMarginBottom: true
            })
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("table", {
          className: "event-table-editor",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("thead", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Was ist angefragt?', 'task-table')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Kontext', 'task-table')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Name der Hochschule', 'task-table')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Vorauss. TN-Anzahl', 'task-table')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Anvisierter Zeitpunkt', 'task-table')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Auftragdetails', 'task-table')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Wer macht\'s?', 'task-table')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Aktionen', 'task-table')
              })]
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("tbody", {
            children: rows.map((row, index) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
              className: row._pending ? 'pending-row' : '',
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.RichText, {
                  value: row.wasAngefragt,
                  onChange: value => updateRow(index, 'wasAngefragt', value),
                  placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Was ist angefragt?', 'task-table')
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.RichText, {
                  value: row.kontext,
                  onChange: value => updateRow(index, 'kontext', value),
                  placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Kontext', 'task-table')
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.RichText, {
                  value: row.nameHochschule,
                  onChange: value => updateRow(index, 'nameHochschule', value),
                  placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Name der Hochschule', 'task-table')
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.RichText, {
                  value: row.tnAnzahl,
                  onChange: value => updateRow(index, 'tnAnzahl', value),
                  placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Vorauss. TN-Anzahl', 'task-table')
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.RichText, {
                  value: row.zeitpunkt,
                  onChange: value => updateRow(index, 'zeitpunkt', value),
                  placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Anvisierter Zeitpunkt', 'task-table')
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.RichText, {
                  value: row.auftragdetails,
                  onChange: value => updateRow(index, 'auftragdetails', value),
                  placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Auftragdetails', 'task-table')
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("td", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
                  children: row.werMacht
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
                  className: "button-row",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
                    disabled: true,
                    variant: "secondary",
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Anmelden (Vorschau)', 'task-table')
                  })
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
                  isDestructive: true,
                  onClick: () => deleteRow(index),
                  variant: "tertiary",
                  iconSize: 16,
                  icon: "trash",
                  title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Zeile löschen', 'task-table')
                })
              })]
            }, index))
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          onClick: addRow,
          variant: "primary",
          className: "add-row-button",
          disabled: !categoryId,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Zeile hinzufügen', 'task-table')
        })]
      }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("style", {
        children: `
                    .pending-row { background-color: rgba(255, 255, 0, 0.1); }
                    .task-table-active-category { margin-bottom: 16px; display: flex; flex-direction: column; gap: 8px; }
                    .task-table-inline-category-select .components-select-control__input { max-width: 260px; }
                `
      })]
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Edit);

/***/ }),

/***/ "./src/task-table/index.js":
/*!*********************************!*\
  !*** ./src/task-table/index.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./style.scss */ "./src/task-table/style.scss");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/task-table/edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./block.json */ "./src/task-table/block.json");
/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */


/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * All files containing `style` keyword are bundled together. The code used
 * gets applied both to the front of your site and to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */


/**
 * Internal dependencies
 */



/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_3__.name, {
  /**
   * @see ./edit.js
   */
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"]
});

/***/ }),

/***/ "./src/task-table/style.scss":
/*!***********************************!*\
  !*** ./src/task-table/style.scss ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "@wordpress/api-fetch":
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["apiFetch"];

/***/ }),

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ ((module) => {

module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"task-table/index": 0,
/******/ 			"task-table/style-index": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunktask_table"] = globalThis["webpackChunktask_table"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["task-table/style-index"], () => (__webpack_require__("./src/task-table/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map