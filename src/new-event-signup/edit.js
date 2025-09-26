import { useBlockProps } from '@wordpress/block-editor';

const Edit = () => {
    return (
        <div { ...useBlockProps({ className: 'new-event-signup-editor' }) }>
            <h3>Neue Veranstaltungen abonnieren</h3>
            <p>Dieses Formular erscheint im Frontend zur Anmeldung für Benachrichtigungen.</p>
        </div>
    );
};

export default Edit;
