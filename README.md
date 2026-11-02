# DSFR Card Image Bundle

Images adaptées aux cartes du [Système de Design de l'État (DSFR)](https://www.systeme-de-design.gouv.fr/) pour
Symfony : chaque image est recadrée ou réduite selon un **profil** (cadre, mode, format, qualité), convertie en WebP,
enregistrée à part des originaux et réutilisée ensuite.

```twig
<div class="fr-card__img">
    <img class="fr-responsive-img fr-ratio-16x9" src="{{ card_image_url('images/equipe.jpg', 'card') }}" alt="">
</div>
```

## Sommaire

1. [Objectifs](#objectifs)
2. [Fonctionnalités](#fonctionnalités)
3. [Prérequis](#prérequis)
4. [Installation](#installation)
5. [Enregistrement du bundle](#enregistrement-du-bundle)
6. [Configuration](#configuration)
7. [Utilisation dans Twig](#utilisation-dans-twig)
8. [Profils](#profils)
9. [Stockage des images générées](#stockage-des-images-générées)
10. [Régénération](#régénération)
11. [Gestion des erreurs](#gestion-des-erreurs)
12. [Commande](#commande)
13. [Tests et qualité](#tests-et-qualité)
14. [Limites connues](#limites-connues)
15. [Compatibilité](#compatibilité)

## Objectifs

Les composants Carte du DSFR affichent l'image dans un cadre aux proportions fixes (`fr-ratio-16x9` par défaut ;
`32x9`, `3x2`, `4x3`, `1x1`, `3x4`, `2x3` en option), avec `object-fit: cover`. Envoyer au navigateur une photo de
4 000 pixels pour une carte de 400 pixels de large alourdit la page, et une image aux mauvaises proportions est coupée
au hasard du cadre. Le bundle produit, côté serveur, l'image dont la carte a besoin :

- aux proportions du cadre (recadrage centré) ou entière (logo, schéma) ;
- à la bonne taille, jamais agrandie (pas d'image floue) ;
- au format WebP, plus léger, la transparence étant conservée ;
- une seule fois : l'image générée est réutilisée, l'original n'est jamais modifié.

Le bundle n'impose ni entité, ni Doctrine, ni structure de dossiers, ni balisage HTML : il fournit l'adresse de
l'image, le balisage DSFR reste celui de l'application.

## Fonctionnalités

- **Profils** déclarés dans la configuration : largeur et hauteur du cadre, mode `cover` (recadrage) ou `contain`
  (image entière), format de sortie, qualité.
- **Traitement GD** (extension native de PHP) : lecture JPEG, PNG, GIF, WebP ; sortie WebP, JPEG ou PNG ;
  transparence conservée (fond blanc en JPEG) ; photos JPEG remises à l'endroit selon l'orientation EXIF.
- **Cache déterministe** : un fichier par original et par réglage de profil, écrit de façon atomique ; un original
  remplacé ou un profil modifié donne une nouvelle image.
- **Réponse HTTP** (`ImageResponseFactory`) : image générée, sinon l'original (traitement impossible), sinon une image
  de remplacement neutre (fichier absent) ; cache navigateur, `Last-Modified`, `304 Not Modified`.
- **Route** facultative pour les images d'un dossier (par défaut `public/`), protégée contre la sortie du dossier.
- **Fonction Twig** `card_image_url(image, profil)` et service `ImageUrlGenerator`, avec des **résolveurs d'adresse**
  pour les objets de l'application (entité de médiathèque, pièce jointe…).
- **Commande** `dsfr-card-image:purge`.

## Prérequis

- PHP 8.3 ou plus ;
- Symfony 7.4 ou 8.x (FrameworkBundle) ;
- extension **GD** avec la prise en charge de WebP (recommandée ; sans GD, les originaux sont servis) ;
- extension **exif** (facultative) : orientation des photos JPEG ;
- **Twig** (TwigBundle) pour la fonction `card_image_url()`, **symfony/console** pour la commande.

Vérifier GD et WebP : `php -r 'var_dump(extension_loaded("gd") && (imagetypes() & IMG_WEBP));'`.
Sous Debian ou Ubuntu : `apt install php8.3-gd` (adapter la version).

## Installation

Tant que le paquet n'est pas publié sur Packagist, déclarer le dépôt Git dans le `composer.json` de l'application :

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/yassrzg/DsfrCardImageBundle.git" }
    ]
}
```

puis :

```shell
composer require yassrzg/dsfr-card-image-bundle:dev-main
```

Une fois une version publiée (étiquette `v1.0.0`…), `composer require yassrzg/dsfr-card-image-bundle` suffit.

## Enregistrement du bundle

Avec Symfony Flex, le bundle est ajouté à `config/bundles.php`. Sinon :

```php
// config/bundles.php
return [
    // …
    Yassrzg\DsfrCardImageBundle\DsfrCardImageBundle::class => ['all' => true],
];
```

Pour servir les images d'un dossier (option `source_directory`) par la route du bundle, importer ses routes :

```yaml
# config/routes/dsfr_card_image.yaml
dsfr_card_image:
    resource: '@DsfrCardImageBundle/config/routes.php'
    prefix: /card-image
```

La route est `GET|HEAD <préfixe>/{profile}/{path}` (nom : `dsfr_card_image`). Les règles d'accès se posent dans
l'application, par exemple :

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/card-image, roles: IS_AUTHENTICATED }
```

## Configuration

Toutes les options, avec leurs valeurs par défaut :

```yaml
# config/packages/dsfr_card_image.yaml
dsfr_card_image:
    # Dossier des images générées (hors de public/ : elles sont servies par une réponse HTTP).
    cache_directory: '%kernel.project_dir%/var/card_images'
    # Dossier des originaux servis par la route du bundle (chemins relatifs donnés à card_image_url()).
    source_directory: '%kernel.project_dir%/public'
    # Au-delà (largeur × hauteur), l'original est servi sans traitement (protection de la mémoire).
    max_pixels: 40000000
    # Format par défaut des profils : auto (WebP si le serveur le gère, sinon PNG ou JPEG), webp, jpeg, png.
    format: auto
    # Qualité par défaut des profils, de 1 à 100 (PNG : niveau de compression, sans perte).
    quality: 82
    http_cache:
        # Durée de cache navigateur des images, en secondes (puis revalidation par Last-Modified).
        max_age: 3600
        # true : cache partagé (proxy, CDN) autorisé ; seulement si les images sont publiques.
        public: false
    # Profils : remplacent entièrement les profils par défaut dès qu'un profil est déclaré.
    profiles:
        card: { width: 800, height: 450, mode: cover }
        card_contain: { width: 800, height: 450, mode: contain }
```

Une configuration invalide (dimension nulle, mode ou format inconnu, qualité hors limites, nom de profil invalide) est
refusée au démarrage de l'application.

## Utilisation dans Twig

`card_image_url(image, profil)` renvoie l'adresse de l'image adaptée au profil, pour l'attribut `src`.

**Chemin relatif au dossier source** (route du bundle importée) :

```twig
<div class="fr-card fr-enlarge-link">
    <div class="fr-card__body">
        <div class="fr-card__content">
            <h3 class="fr-card__title"><a href="{{ path('team') }}">L'équipe</a></h3>
        </div>
    </div>
    <div class="fr-card__header">
        <div class="fr-card__img">
            <img class="fr-responsive-img fr-ratio-16x9" src="{{ card_image_url('images/equipe.jpg', 'card') }}"
                 alt="" width="800" height="450" loading="lazy">
        </div>
    </div>
</div>
```

**Objet de l'application** (entité, document…) : déclarer un résolveur d'adresse. Le premier résolveur qui prend
l'image en charge est utilisé ; celui du bundle (chemins) passe en dernier (priorité −100).

```php
namespace App\Image;

use App\Entity\Picture;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Yassrzg\DsfrCardImageBundle\Url\ImageUrlResolverInterface;

final readonly class PictureUrlResolver implements ImageUrlResolverInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function supports(mixed $image): bool
    {
        return $image instanceof Picture;
    }

    public function url(mixed $image, string $profile): string
    {
        return $this->urlGenerator->generate('picture_image', ['id' => $image->getId(), 'profile' => $profile]);
    }
}
```

Avec l'autoconfiguration (par défaut dans `config/services.yaml`), la classe est prise en compte sans autre déclaration.
Le contrôleur de l'application garde ses propres règles d'accès et confie la réponse au bundle :

```php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Yassrzg\DsfrCardImageBundle\Http\ImageResponseFactory;

#[Route('/picture/{id}/{profile}', name: 'picture_image', methods: ['GET'])]
public function image(Request $request, Picture $picture, string $profile, ImageResponseFactory $responses): Response
{
    $this->denyAccessUnlessGranted('VIEW', $picture);

    // Chemin absolu de l'original, ou null s'il n'existe pas (image de remplacement).
    return $responses->create($request, $this->storage->absolutePath($picture), $profile);
}
```

Hors de Twig (contrôleur, API), le service `Yassrzg\DsfrCardImageBundle\Url\ImageUrlGenerator` donne la même adresse
(`generate($image, $profile)`), et `Yassrzg\DsfrCardImageBundle\ImageVariantGenerator` le fichier généré
(`generate($cheminAbsolu, $profil)` → `ImageVariant` : `path`, `format`, `mimeType()`).

### Image entière (logo)

Le DSFR affiche l'image de carte en `object-fit: cover`. Pour une image produite par un profil `contain` (logo,
schéma), l'afficher entière, par exemple :

```css
.fr-card__img img.card-image--contain {
    object-fit: contain;
    padding: 0.5rem;
    background-color: var(--background-default-grey);
}
```

## Profils

| Option    | Valeurs                          | Défaut          | Rôle |
|-----------|----------------------------------|-----------------|------|
| `width`   | entier ≥ 1 (obligatoire)         |                 | Largeur maximale de l'image produite, en pixels |
| `height`  | entier ≥ 1 (obligatoire)         |                 | Hauteur maximale, en pixels |
| `mode`    | `cover`, `contain`               | `cover`         | `cover` : recadrage centré aux proportions du cadre ; `contain` : image entière réduite dans le cadre |
| `format`  | `auto`, `webp`, `jpeg`, `png`    | option `format` | Format de sortie |
| `quality` | 1 à 100                          | option `quality`| Qualité de compression |

Les noms de profils comportent des lettres minuscules, des chiffres, `_` et `-`.

Une image n'est **jamais agrandie** : une source plus petite que le cadre garde sa taille (en `cover`, elle est
seulement recadrée aux bonnes proportions).

**Choisir les dimensions** : largeur affichée de la carte × 2 (écrans haute densité), hauteur tirée des proportions
du cadre DSFR. Exemples pour une grille `fr-col-md-4` d'un conteneur DSFR (environ 400 px) :

```yaml
dsfr_card_image:
    profiles:
        card: { width: 800, height: 450 }                 # fr-ratio-16x9 (défaut des cartes)
        card_wide: { width: 1600, height: 450 }           # fr-ratio-32x9
        card_4x3: { width: 800, height: 600 }             # fr-ratio-4x3
        card_square: { width: 600, height: 600 }          # fr-ratio-1x1
        logo: { width: 800, height: 450, mode: contain }  # logo ou schéma, affiché entier
        thumb: { width: 240, height: 135, format: jpeg, quality: 75 }
```

## Stockage des images générées

- Chemin : `<cache_directory>/<profil>/<clé>.<extension>` (par défaut `var/card_images/card/3f9a….webp`).
- Clé : SHA-1 du chemin réel de l'original, de sa date de modification, de sa taille et des réglages du profil
  (dimensions, mode, format, qualité). Deux originaux différents n'ont jamais la même clé ; un original remplacé ou un
  réglage modifié donne une nouvelle clé, donc une nouvelle image.
- Écriture dans un fichier temporaire renommé ensuite : une requête simultanée ne lit jamais une image incomplète.
- Les originaux ne sont ni modifiés, ni déplacés, ni supprimés.
- Le dossier doit être accessible en écriture par l'utilisateur du serveur web (PHP-FPM).

## Régénération

Rien à faire quand un original ou un profil change : la nouvelle image est générée au premier affichage. Les
anciennes images restent dans le dossier jusqu'à la purge :

```shell
php bin/console dsfr-card-image:purge          # toutes les images générées
php bin/console dsfr-card-image:purge card     # celles du profil « card »
```

Elles sont recréées à la demande. Après un changement de version de GD, purger pour profiter du nouvel encodeur.

## Gestion des erreurs

Toutes les exceptions du bundle implémentent `Yassrzg\DsfrCardImageBundle\Exception\ExceptionInterface` :

| Exception                      | Cas |
|--------------------------------|-----|
| `InvalidProfileException`      | Profil mal défini (nom, dimensions, qualité) |
| `UnknownProfileException`      | Profil absent de la configuration |
| `SourceNotFoundException`      | Original absent ou illisible |
| `UnsupportedImageException`    | Fichier qui n'est pas une image JPEG, PNG, GIF ou WebP ; image au-delà de `max_pixels` ; format de sortie non géré par le GD du serveur |
| `ImageProcessingException`     | GD absent, image corrompue, dossier des images générées non accessible en écriture |
| `UnresolvableImageException`   | Aucun résolveur d'adresse pour l'objet passé à `card_image_url()` |

`ImageVariantGenerator::generate()` lève ces exceptions. `ImageResponseFactory::create()` les traduit pour le
navigateur :

| Situation | Réponse |
|---|---|
| Image générée | L'image (`image/webp`…), cache navigateur, `Last-Modified` |
| Profil inconnu | 404 |
| Original absent | Image de remplacement SVG neutre (cache 5 minutes) |
| Traitement impossible, l'original est une image | L'original, avec un avertissement dans le journal (canal `dsfr_card_image`) |
| Traitement impossible, le fichier n'est pas une image | Image de remplacement : le contenu du fichier n'est jamais servi |

Dans un gabarit, un profil inconnu ou un objet sans résolveur provoque une erreur dès l'affichage de la page.

## Commande

| Commande | Rôle |
|---|---|
| `dsfr-card-image:purge [profil]` | Supprime les images générées (toutes, ou celles d'un profil) ; les originaux ne sont pas touchés |

## Tests et qualité

```shell
composer install
vendor/bin/phpunit                 # tests unitaires et fonctionnels
vendor/bin/phpstan analyse         # analyse statique, niveau max
vendor/bin/php-cs-fixer fix --dry-run --diff
```

Les tests couvrent le calcul des dimensions (recadrage, ajustement, images minuscules ou très grandes, proportions
quelconques), les formats d'entrée et de sortie, la transparence, l'orientation EXIF, les fichiers absents, illisibles,
corrompus ou non images, les droits d'écriture, la réutilisation et la régénération des images, la préservation des
originaux, la configuration (valeurs par défaut et valeurs invalides), l'enregistrement des services, la fonction Twig,
la route (images, image de remplacement, `304`, chemins hors du dossier) et la commande. Les tests qui produisent des
images demandent l'extension GD.

## Limites connues

- Traitement par GD uniquement : pas de format AVIF ni HEIC en entrée, pas de profils de couleur ICC (les couleurs
  d'une photo en Adobe RGB peuvent paraître ternes), animation des GIF et WebP perdue (première image).
- Recadrage toujours centré (pas de point d'intérêt).
- Génération à la première demande : le premier affichage d'une grande image prend un peu de temps.
- Les métadonnées (EXIF, IPTC) ne sont pas recopiées dans les images générées.
- Les images générées pour d'anciens réglages ou d'anciens originaux restent jusqu'à la purge.
- SVG non traités (servis seulement par l'application, pas par la route du bundle).

## Compatibilité

| Composant | Versions | Vérifié avec |
|---|---|---|
| PHP | 8.3, 8.4 | 8.3.36, 8.4.27 |
| Symfony | 7.4, 8.x | 7.4.0 (versions les plus anciennes acceptées), 7.4.22, 8.0.15, 8.1.10 |
| Twig | 3.21 et plus | 3.27 |
| GD | WebP, JPEG, PNG, GIF | GD 2.3.3 |

## Licence

GPL-2.0-or-later, voir [LICENSE](LICENSE).

## Auteur

Conçu et développé par yassine REZGUI ([github.com/yassrzg](https://github.com/yassrzg)).
