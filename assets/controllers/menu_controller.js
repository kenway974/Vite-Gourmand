import { Controller } from '@hotwired/stimulus';

/*
 * Bascule de la navigation sur petit écran.
 *
 * Le repli n'est activé qu'ici, au démarrage du contrôleur : la classe
 * .menu-js posée sur la barre est ce qui autorise le CSS à masquer la
 * navigation. Tant que ce script n'a pas tourné — désactivé, en échec de
 * chargement, réseau coupé — la navigation reste dépliée et le bouton
 * masqué. Un menu enfermé derrière une bascule morte rendrait le site
 * inutilisable, ce qui est pire que pas de bascule du tout.
 */
export default class extends Controller {
    static targets = ['panneau', 'bouton', 'intitule'];

    connect() {
        this.element.classList.add('menu-js');
        this.fermer();

        // Repasser en grand écran doit rouvrir la navigation : sinon elle
        // resterait masquée alors que le bouton, lui, a disparu.
        this.requete = window.matchMedia('(max-width: 860px)');
        this.surChangement = () => this.fermer();
        this.requete.addEventListener('change', this.surChangement);
    }

    disconnect() {
        this.element.classList.remove('menu-js');
        this.requete?.removeEventListener('change', this.surChangement);
    }

    basculer() {
        this.ouvert ? this.fermer() : this.ouvrir();
    }

    ouvrir() {
        this.majEtat(true);
    }

    fermer() {
        this.majEtat(false);
    }

    get ouvert() {
        return this.panneauTarget.dataset.ouvert === 'true';
    }

    majEtat(ouvert) {
        this.panneauTarget.dataset.ouvert = ouvert ? 'true' : 'false';
        this.boutonTarget.setAttribute('aria-expanded', ouvert ? 'true' : 'false');

        // L'intitulé change avec l'état : une icône seule ne dit pas à un
        // lecteur d'écran ce que le bouton va faire.
        if (this.hasIntituleTarget) {
            this.intituleTarget.textContent = ouvert ? 'Fermer le menu' : 'Ouvrir le menu';
        }
    }
}
