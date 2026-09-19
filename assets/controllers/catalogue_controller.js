import { Controller } from '@hotwired/stimulus';

/*
 * Filtrage du catalogue sans rechargement de page.
 *
 * Le formulaire de filtres et les liens de pagination fonctionnent déjà en
 * simples GET : c'est ce qui les rend utilisables sans JavaScript. Ce
 * contrôleur ne remplace pas ce chemin, il l'intercepte : soumission du
 * formulaire ou clic sur un lien de la zone de résultats déclenchent une
 * requête vers la même URL, marquée par un en-tête que CatalogueController
 * reconnaît pour ne renvoyer que le fragment de résultats.
 *
 * L'URL affichée est mise à jour via l'historique, pour qu'un rechargement
 * ou un retour arrière retombent sur le même filtrage — et sur une requête
 * classique en page complète, puisque c'est alors une vraie navigation.
 */
export default class extends Controller {
    static targets = ['resultats'];

    connect() {
        this.surClic = (evenement) => this.clicResultats(evenement);
        this.surPopState = () => this.charger(window.location.href, false);

        this.resultatsTarget.addEventListener('click', this.surClic);
        window.addEventListener('popstate', this.surPopState);
    }

    disconnect() {
        this.resultatsTarget.removeEventListener('click', this.surClic);
        window.removeEventListener('popstate', this.surPopState);
    }

    filtrer(evenement) {
        evenement.preventDefault();
        const params = new URLSearchParams(new FormData(evenement.currentTarget));
        this.charger(`${evenement.currentTarget.action}?${params}`);
    }

    /*
     * Délégation limitée à la pagination : le conteneur contient aussi les
     * liens vers chaque fiche menu et « Élargir la recherche », qui doivent
     * rester de vraies navigations, pas un filtrage.
     */
    clicResultats(evenement) {
        const lien = evenement.target.closest('a');

        if (!lien || !lien.closest('.pagination')) {
            return;
        }

        evenement.preventDefault();
        this.charger(lien.href);
    }

    async charger(url, pousserHistorique = true) {
        this.resultatsTarget.setAttribute('aria-busy', 'true');

        try {
            const reponse = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (!reponse.ok) {
                throw new Error(`Réponse ${reponse.status}`);
            }

            this.resultatsTarget.innerHTML = await reponse.text();

            if (pousserHistorique) {
                history.pushState({}, '', url);
            }

            // Un lecteur d'écran doit savoir que le contenu a changé : le
            // compteur en tête du fragment porte déjà role="status", il
            // suffit de lui redonner le focus pour qu'il soit annoncé.
            this.resultatsTarget.querySelector('[role="status"]')?.focus();
        } catch {
            // La requête a échoué (réseau coupé, erreur serveur) : une vraie
            // navigation reste le filet de sécurité, plutôt qu'un catalogue
            // figé sans explication.
            window.location.href = url;
        } finally {
            this.resultatsTarget.removeAttribute('aria-busy');
        }
    }
}
