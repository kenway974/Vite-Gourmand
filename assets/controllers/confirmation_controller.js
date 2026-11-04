import { Controller } from '@hotwired/stimulus';

/*
 * Demande confirmation avant d'envoyer un formulaire (suppressions).
 *
 * Remplace les attributs onsubmit="return confirm(...)" : la
 * Content-Security-Policy interdit tout JavaScript écrit dans le HTML.
 *
 *   <form data-controller="confirmation"
 *         data-confirmation-message-value="Supprimer ?"
 *         data-action="submit->confirmation#demander">
 */
export default class extends Controller {
    static values = { message: String };

    demander(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
