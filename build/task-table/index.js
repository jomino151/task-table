/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

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
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);





const Edit = ({
  attributes,
  setAttributes
}) => {
  const {
    rows = []
  } = attributes;
  const [previousRows, setPreviousRows] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)([]);
  const [notificationSent, setNotificationSent] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(false);
  const [notificationError, setNotificationError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)('');
  const [debugInfo, setDebugInfo] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(null);
  const [initialLoad, setInitialLoad] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(true);
  const [notificationQueue, setNotificationQueue] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)([]);
  const [isLoading, setIsLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(true);
  const rowUpdateTimers = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useRef)({});

  // First-time setup - fetch data from database
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
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
      const events = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
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
      setAttributes({
        rows: formattedEvents
      });
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
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    if (notificationQueue.length > 0) {
      const row = notificationQueue[0];
      console.log('Task Table: Processing test notification for row:', row);
      triggerNewEventNotification(row).then(result => {
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
      }).catch(error => {
        console.error('Task Table: API error:', error);
        setNotificationError('API-Fehler: ' + (error.message || 'Unbekannter Fehler'));
        setDebugInfo({
          error: error.toString(),
          stack: error.stack
        });
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
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: '/task-table/v1/trigger-event-notification',
        method: 'POST',
        data: {
          event: row,
          _trigger_source: 'block_editor_test_button',
          // Changed to indicate this is a manual test
          _test_mode: true // Add flag to indicate this is just a test
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
          formData.append('test_mode', '1'); // Add test mode flag
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
  const testNotification = row => {
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
      _pending: true // Flag to indicate this row is new and pending save
    };

    // Add to UI immediately for better UX
    const newRows = [...rows, newRow];
    setAttributes({
      rows: newRows
    });
    try {
      // Save to database
      const savedEvent = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
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
            werMacht: savedEvent.event.wer_macht
          };
        }
        return row;
      });
      setAttributes({
        rows: updatedRows
      });
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
    newRows[index] = {
      ...newRows[index],
      [field]: value
    };
    setAttributes({
      rows: newRows
    });

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
        const completeRow = {
          ...newRows[index]
        };

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
        const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
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
          setAttributes({
            rows: refreshedRows
          });
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
  const deleteRow = async index => {
    if (window.confirm('Are you sure you want to delete this event?')) {
      const rowToDelete = rows[index];
      const rowId = rowToDelete.id;

      // Remove from UI immediately for better UX
      const newRows = rows.filter((_, i) => i !== index);
      setAttributes({
        rows: newRows
      });

      // Don't try to delete from database if it's a pending row
      if (rowToDelete._pending) {
        return;
      }
      try {
        await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
          path: `/task-table/v1/events/${rowId}`,
          method: 'DELETE'
        });
        console.log('Task Table: Deleted event with ID:', rowId);
      } catch (error) {
        console.error('Task Table: Failed to delete row from database:', error);

        // Put the row back on error
        setAttributes({
          rows: [...rows]
        });
        setNotificationError('Failed to delete row from database: ' + (error.message || 'Unknown error'));
        setTimeout(() => setNotificationError(''), 8000);
      }
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
    ...(0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.useBlockProps)(),
    children: [isLoading && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      style: {
        padding: '20px',
        textAlign: 'center'
      },
      children: "Lade Veranstaltungen..."
    }), notificationSent && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "notification-feedback success",
      style: {
        backgroundColor: '#d4edda',
        color: '#155724',
        padding: '10px',
        marginBottom: '15px',
        borderRadius: '4px'
      },
      children: "Test-Benachrichtigung erfolgreich durchgef\xFChrt!"
    }), notificationError && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "notification-feedback error",
      style: {
        backgroundColor: '#f8d7da',
        color: '#721c24',
        padding: '10px',
        marginBottom: '15px',
        borderRadius: '4px'
      },
      children: notificationError
    }), debugInfo && (typeof WP_DEBUG !== 'undefined' && WP_DEBUG || true) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "debug-info",
      style: {
        backgroundColor: '#f0f8ff',
        border: '1px solid #ccc',
        padding: '10px',
        marginBottom: '15px',
        fontSize: '12px',
        maxHeight: '150px',
        overflow: 'auto'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
        children: "Debug Info:"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("pre", {
        children: JSON.stringify(debugInfo, null, 2)
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("table", {
      className: "event-table-editor",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("thead", {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("tr", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
            children: "Was ist angefragt?"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
            children: "Kontext"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
            children: "Name der Hochschule"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
            children: "Vorauss. TN-Anzahl"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
            children: "Anvisierter Zeitpunkt"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
            children: "Auftragdetails"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
            children: "Wer macht's?"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("th", {
            children: "Aktionen"
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("tbody", {
        children: rows.map((row, index) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("tr", {
          className: row._pending ? 'pending-row' : '',
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.RichText, {
              value: row.wasAngefragt,
              onChange: value => updateRow(index, 'wasAngefragt', value),
              placeholder: "Was ist angefragt?"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.RichText, {
              value: row.kontext,
              onChange: value => updateRow(index, 'kontext', value),
              placeholder: "Kontext"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.RichText, {
              value: row.nameHochschule,
              onChange: value => updateRow(index, 'nameHochschule', value),
              placeholder: "Name der Hochschule"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.RichText, {
              value: row.tnAnzahl,
              onChange: value => updateRow(index, 'tnAnzahl', value),
              placeholder: "Vorauss. TN-Anzahl"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.RichText, {
              value: row.zeitpunkt,
              onChange: value => updateRow(index, 'zeitpunkt', value),
              placeholder: "Anvisierter Zeitpunkt"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.RichText, {
              value: row.auftragdetails,
              onChange: value => updateRow(index, 'auftragdetails', value),
              placeholder: "Auftragdetails"
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("td", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
              children: row.werMacht
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
              className: "button-row",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
                disabled: true,
                variant: "secondary",
                children: "Anmelden (Vorschau)"
              }), (row.wasAngefragt || row.kontext) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
                variant: "secondary",
                onClick: () => testNotification(row),
                style: {
                  marginTop: '5px',
                  fontSize: '10px'
                },
                children: "Nur Test (keine echte E-Mail)"
              })]
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("td", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
              isDestructive: true,
              onClick: () => deleteRow(index),
              variant: "tertiary",
              iconSize: 16,
              icon: "trash",
              title: "Delete row"
            })
          })]
        }, index))
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
      onClick: addRow,
      variant: "primary",
      className: "add-row-button",
      children: "Zeile hinzuf\xFCgen"
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("style", {
      children: `
                .pending-row {
                    background-color: rgba(255, 255, 0, 0.1);
                }
                `
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

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

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

/***/ "./src/task-table/block.json":
/*!***********************************!*\
  !*** ./src/task-table/block.json ***!
  \***********************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"task-table/task-table","version":"0.1.0","title":"task-Table","category":"text","icon":"table","description":"Creates a dynamic table to communicate task to multis.","example":{},"supports":{"html":false},"attributes":{"rows":{"type":"array","default":[]}},"textdomain":"task-table","editorScript":"file:./index.js","editorStyle":"file:./index.css","style":"file:./style-index.css","render":"file:./render.php","viewScript":"file:./view.js"}');

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