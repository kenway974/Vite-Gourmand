import './stimulus_bootstrap.js';

// La feuille de style est liée directement dans base.html.twig, et non
// importée ici : AssetMapper remplacerait l'import par un module « data: »,
// que la Content-Security-Policy (script-src) bloque.
