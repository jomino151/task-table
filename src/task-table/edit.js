import { useBlockProps, RichText } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';

const Edit = ({ attributes, setAttributes }) => {
    const { rows = [] } = attributes;

    const generateEventId = () => {
        // Generate a random number within the mediumint range
        return Math.floor(Math.random() * 8388607);
    };

    const addRow = () => {
        const newRows = [...rows, { 
            id: generateEventId(), // Use a unique identifier for each row within the mediumint range
            wasAngefragt: '', 
            kontext: '', 
            nameHochschule: '', 
            tnAnzahl: '', 
            zeitpunkt: '', 
            auftragdetails: '',
            werMacht: '' // This will be filled dynamically when someone signs up
        }];
        setAttributes({ rows: newRows });
    };

    const updateRow = (index, field, value) => {
        const newRows = [...rows];
        newRows[index][field] = value;
        setAttributes({ rows: newRows });
    };

    return (
        <div { ...useBlockProps() }>
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
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr key={index}>
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
                                {/* Placeholder for the "Sign Up" button in the editor */}
                                <Button disabled variant="secondary">
                                    Sign Up (Preview)
                                </Button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <Button onClick={addRow} variant="primary" className="add-row-button">
                Add Row
            </Button>
        </div>
    );
};

export default Edit;