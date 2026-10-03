<script setup>
import LegalPage from './LegalPage.vue'
</script>

<template>
    <LegalPage eyebrow="Informations" title="Mentions légales" updated-at="août 2026">
        <section>
            <h2>Éditeur du site</h2>
            <p>
                Le site <strong>Générique</strong> (generique.theo-birost.fr) est édité
                par <span class="todo">NOM ET PRÉNOM / RAISON SOCIALE</span>,
                <span class="todo">STATUT JURIDIQUE</span>.
            </p>
            <ul>
                <li>Adresse : <span class="todo">ADRESSE POSTALE</span></li>
                <li>Contact : <span class="todo">ADRESSE E-MAIL DE CONTACT</span></li>
                <li>
                    Numéro SIREN / SIRET :
                    <span class="todo">À COMPLÉTER SI ACTIVITÉ DÉCLARÉE</span>
                </li>
                <li>
                    Directeur de la publication :
                    <span class="todo">NOM DU DIRECTEUR DE PUBLICATION</span>
                </li>
            </ul>
        </section>

        <section>
            <h2>Hébergement</h2>
            <p>
                Le site et son interface d'administration sont hébergés sur un serveur
                privé virtuel opéré par <strong>OVH SAS</strong>, 2 rue Kellermann,
                59100 Roubaix, France — <a href="https://www.ovhcloud.com" rel="noopener noreferrer" target="_blank">ovhcloud.com</a>.
                Le déploiement des conteneurs est orchestré avec Dokploy.
            </p>
        </section>

        <section>
            <h2>Nature du service</h2>
            <p>
                Générique est un projet pédagogique présentant un catalogue de films,
                d'acteurs et de réalisateurs. Les données affichées sont des données de
                démonstration : elles n'ont pas vocation à constituer une source
                d'information fiable ou exhaustive sur les œuvres présentées.
            </p>
        </section>

        <section>
            <h2>Propriété intellectuelle</h2>
            <p>
                La structure du site, son code source et son identité graphique sont la
                propriété de l'éditeur. Les affiches, photographies et éléments relatifs
                aux œuvres cinématographiques restent la propriété de leurs ayants droit
                respectifs et sont utilisés à des fins illustratives dans un cadre
                pédagogique et non commercial.
            </p>
            <p>
                Toute reproduction ou représentation, totale ou partielle, du site à
                d'autres fins est soumise à l'autorisation préalable de l'éditeur.
                Pour toute demande de retrait d'un contenu, écrivez à
                <span class="todo">ADRESSE E-MAIL DE CONTACT</span>.
            </p>
        </section>

        <section>
            <h2>Responsabilité</h2>
            <p>
                L'éditeur s'efforce d'assurer la disponibilité et l'exactitude des
                informations publiées, sans garantie d'absence d'interruption ou
                d'erreur. Sa responsabilité ne saurait être engagée du fait de
                l'utilisation des informations du site, ni du contenu des sites tiers
                accessibles depuis des liens hypertextes.
            </p>
            <p>
                Les avis publiés par les utilisateurs relèvent de la responsabilité de
                leurs auteurs. Tout contenu illicite peut être signalé à l'adresse de
                contact ci-dessus et sera retiré dans les meilleurs délais.
            </p>
        </section>

        <section>
            <h2>Données personnelles</h2>
            <p>
                Le traitement des données personnelles est décrit dans la
                <router-link to="/confidentialite">politique de confidentialité</router-link>.
            </p>
        </section>

        <section>
            <h2>Droit applicable</h2>
            <p>
                Les présentes mentions légales sont soumises au droit français. En cas
                de litige, et à défaut de résolution amiable, les tribunaux français
                sont seuls compétents.
            </p>
        </section>
    </LegalPage>
</template>
