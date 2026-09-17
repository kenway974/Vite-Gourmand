import { Controller } from '@hotwired/stimulus';

/*
 * Bulles de notification.
 *
 * Deux règles guident ce contrôleur, et elles vont à l'encontre de ce qu'on
 * voit souvent :
 *
 *  1. Une bulle d'ERREUR ne disparaît jamais toute seule. Un message qui dit
 *     que quelque chose a échoué doit rester le temps d'être lu, et refermé
 *     par qui l'a lu. Seules les confirmations s'effacent.
 *
 *  2. Le compte à rebours s'arrête au survol et au focus clavier. Sinon une
 *     bulle s'efface pendant qu'on la lit, ou pendant qu'on cherche son
 *     bouton de fermeture à la tabulation.
 *
 * Sans JavaScript, la classe --js n'est pas posée : le CSS remet alors les
 * messages dans le flux de la page. Une bulle flottante que rien ne peut
 * refermer resterait à l'écran et masquerait le contenu.
 */
export default class extends Controller {
    static targets = ['bulle'];
    static values = { delai: { type: Number, default: 6000 } };

    connect() {
        this.element.classList.add('notifications--js');
        this.minuteries = new Map();

        this.bulleTargets.forEach((bulle) => {
            if (bulle.dataset.persistant === 'true') {
                return;
            }
            this.lancer(bulle);
        });
    }

    disconnect() {
        this.element.classList.remove('notifications--js');
        this.minuteries.forEach(clearTimeout);
        this.minuteries.clear();
    }

    lancer(bulle) {
        this.arreter(bulle);
        this.minuteries.set(bulle, setTimeout(() => this.retirer(bulle), this.delaiValue));
    }

    arreter(bulle) {
        const m = this.minuteries.get(bulle);
        if (m) {
            clearTimeout(m);
            this.minuteries.delete(bulle);
        }
    }

    /** Suspend le compte à rebours tant que la bulle est survolée ou focalisée. */
    suspendre(evenement) {
        const bulle = evenement.currentTarget;
        this.arreter(bulle);
    }

    reprendre(evenement) {
        const bulle = evenement.currentTarget;
        if (bulle.dataset.persistant !== 'true') {
            this.lancer(bulle);
        }
    }

    fermer(evenement) {
        this.retirer(evenement.currentTarget.closest('.flash'));
    }

    retirer(bulle) {
        if (!bulle || !bulle.isConnected) {
            return;
        }

        this.arreter(bulle);
        bulle.dataset.sortie = 'true';

        // Retrait à la fin de l'animation, ou tout de suite si le visiteur a
        // demandé moins de mouvement — l'événement ne viendrait alors jamais.
        const reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduit) {
            bulle.remove();

            return;
        }

        bulle.addEventListener('animationend', () => bulle.remove(), { once: true });
    }
}
