<script setup>
import LegalPage from './LegalPage.vue'
import { useConsent } from '../../composables/useConsent'
import { isAnalyticsConfigured } from '../../analytics'

const { status, reset } = useConsent()

const label = {
    granted: 'accepté',
    denied: 'refusé',
}
</script>

<template>
    <LegalPage
        eyebrow="Vos données"
        title="Politique de confidentialité"
        updated-at="août 2026"
    >
        <section>
            <h2>Responsable du traitement</h2>
            <p>
                Le responsable du traitement des données collectées sur
                generique.theo-birost.fr est
                Théo Birost (Birostweb, entrepreneur individuel).
                Pour toute question relative à vos données :
                <a href="mailto:contact@theo-birost.fr">contact@theo-birost.fr</a>.
            </p>
        </section>

        <section>
            <h2>Données collectées et finalités</h2>
            <table>
                <thead>
                    <tr>
                        <th>Données</th>
                        <th>Finalité</th>
                        <th>Base légale</th>
                        <th>Conservation</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Nom, prénom, e-mail, date de naissance, photo de profil</td>
                        <td>Création et gestion du compte utilisateur</td>
                        <td>Exécution du service demandé</td>
                        <td>Jusqu'à la suppression du compte</td>
                    </tr>
                    <tr>
                        <td>Mot de passe (haché, jamais stocké en clair)</td>
                        <td>Authentification</td>
                        <td>Exécution du service demandé</td>
                        <td>Jusqu'à la suppression du compte</td>
                    </tr>
                    <tr>
                        <td>Secret et codes de secours de double authentification</td>
                        <td>Sécurisation du compte (2FA, optionnelle)</td>
                        <td>Intérêt légitime — sécurité</td>
                        <td>Jusqu'à désactivation de la 2FA</td>
                    </tr>
                    <tr>
                        <td>Notes et commentaires publiés</td>
                        <td>Affichage des avis sur les fiches de films</td>
                        <td>Exécution du service demandé</td>
                        <td>Jusqu'à suppression par l'utilisateur</td>
                    </tr>
                    <tr>
                        <td>Adresse IP, journaux techniques</td>
                        <td>Sécurité, limitation du nombre de requêtes, diagnostic</td>
                        <td>Intérêt légitime — sécurité du service</td>
                        <td>12 mois maximum</td>
                    </tr>
                    <tr>
                        <td>Mesure d'audience</td>
                        <td>Statistiques de fréquentation</td>
                        <td>Consentement</td>
                        <td>13 mois maximum</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section>
            <h2>Cookies et stockage local</h2>
            <h3>Strictement nécessaires</h3>
            <p>
                Votre session de connexion est portée par un cookie technique
                (<code>BEARER</code>), déposé par l'API, inaccessible aux scripts de la
                page (HttpOnly), transmis uniquement en HTTPS et limité au site. Il
                expire au bout d'une heure et est supprimé à la déconnexion.
            </p>
            <p>
                Le stockage local de votre navigateur conserve un résumé de session
                (rôles et heure d'expiration, sans jeton), votre avatar en cache et
                votre choix en matière de mesure d'audience. Ces éléments sont
                indispensables au fonctionnement du service et ne requièrent pas de
                consentement. Le résumé de session et l'avatar sont effacés à la
                déconnexion ; votre choix de mesure d'audience est conservé, afin
                qu'un refus ne soit pas perdu, et peut être modifié à tout moment
                ci-dessous.
            </p>

            <h3>Mesure d'audience</h3>
            <p v-if="isAnalyticsConfigured()">
                Aucun script de mesure n'est chargé tant que vous ne l'avez pas accepté.
                Votre choix actuel :
                <strong>{{ label[status] ?? 'non exprimé' }}</strong>.
            </p>
            <p v-else>
                Aucun outil de mesure d'audience n'est actuellement déployé sur ce site :
                aucun traceur tiers n'est déposé sur votre terminal.
            </p>
            <p v-if="status">
                <button type="button" class="btn btn-ghost" @click="reset">
                    Modifier mon choix
                </button>
            </p>
        </section>

        <section>
            <h2>Destinataires</h2>
            <p>
                Les données sont traitées par l'éditeur du site et hébergées chez OVH
                SAS (France, Union européenne). Aucune donnée n'est cédée ni louée.
            </p>
            <p>
                Vérification des mots de passe : lors de la création d'un mot de passe,
                le serveur interroge le service tiers « Pwned Passwords »
                (api.pwnedpasswords.com) pour vérifier que ce mot de passe ne figure pas
                dans une fuite de données connue. Seuls les 5 premiers caractères de
                l'empreinte SHA-1 du mot de passe sont transmis (méthode dite de
                k-anonymat) ; ni le mot de passe, ni votre e-mail ne sont envoyés. Le
                serveur de ce site est en revanche vu comme émetteur de la requête.
            </p>
        </section>

        <section>
            <h2>Vos droits</h2>
            <p>
                Conformément au Règlement général sur la protection des données et à la
                loi Informatique et Libertés, vous disposez des droits d'accès, de
                rectification, d'effacement, de limitation, d'opposition et de
                portabilité de vos données.
            </p>
            <ul>
                <li>
                    Vos informations de compte sont consultables et modifiables depuis
                    votre <router-link to="/profile">profil</router-link>.
                </li>
                <li>
                    La suppression du compte depuis le profil efface vos données
                    personnelles et les avis qui y sont rattachés.
                </li>
                <li>
                    Pour toute autre demande, écrivez à
                    <a href="mailto:contact@theo-birost.fr">contact@theo-birost.fr</a>. Une réponse
                    vous sera apportée sous un mois.
                </li>
            </ul>
            <p>
                Vous pouvez également introduire une réclamation auprès de la CNIL —
                <a href="https://www.cnil.fr" rel="noopener noreferrer" target="_blank">cnil.fr</a>.
            </p>
        </section>

        <section>
            <h2>Sécurité</h2>
            <p>
                Les échanges avec le site sont chiffrés en HTTPS. Les mots de passe sont
                hachés avec un algorithme à coût configurable et ne sont jamais
                réversibles. La double authentification par application (TOTP) est
                proposée à tous les comptes depuis le profil.
            </p>
        </section>
    </LegalPage>
</template>
