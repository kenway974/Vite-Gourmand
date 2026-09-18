import { Controller } from '@hotwired/stimulus';

/*
 * Repli des filtres secondaires sur petit écran.
 *
 * Le catalogue compte dix thèmes, cinq régimes, un palier de convives et un
 * tri. Déroulés, ils occupent tout le premier écran d'un téléphone : on
 * scrolle longtemps avant d'atteindre le premier menu. La maquette mobile ne
 * garde d'ailleurs que la recherche et les thèmes, le reste derrière
 * « Filtrer ».
 *
 * Même principe que la navigation : c'est ce contrôleur qui pose la classe
 * autorisant le CSS à replier. Sans JavaScript, tous les filtres restent
 * visibles — un panneau de filtres enfermé derrière un bouton inerte rendrait
 * le catalogue inutilisable, et c'est la seule porte d'entrée vers un menu.
 *
 * Le repli n'est jamais appliqué quand un filtre secondaire est déjà actif :
 * masquer un filtre en vigueur ferait croire à un catalogue incomplet sans
 * dire pourquoi.
 */
export default class extends Controller {
    static targets = ['panneau', 'bascule', 'compteur'];
    static values = { actifs: { type: Number, default: 0 } };

    connect() {
        this.element.classList.add('filtres--js');

        this.requete = window.matchMedia('(max-width: 820px)');
        this.surChangement = () => this.appliquer();
        this.requete.addEventListener('change', this.surChangement);

        this.appliquer();
    }

    disconnect() {
        this.element.classList.remove('filtres--js');
        this.requete?.removeEventListener('change', this.surChangement);
    }

    /** Au large, le panneau est toujours ouvert : il n'y a rien à replier. */
    appliquer() {
        const replier = this.requete.matches && this.actifsValue === 0;
        this.majEtat(!replier);
    }

    basculer() {
        this.majEtat(this.panneauTarget.dataset.ouvert !== 'true');
    }

    majEtat(ouvert) {
        this.panneauTarget.dataset.ouvert = ouvert ? 'true' : 'false';
        this.basculeTarget.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    }
}
