# Changements

## 1.0.0 — non publiée

- Profils de traitement configurables (`dsfr_card_image.profiles`) : cadre, mode `cover` (recadrage centré) ou
  `contain` (image entière), format (`auto`, `webp`, `jpeg`, `png`), qualité ; jamais d'agrandissement.
- Traitement par l'extension GD : JPEG, PNG, GIF, WebP en entrée ; transparence conservée ; orientation EXIF des photos
  JPEG ; protection de la mémoire (`max_pixels`).
- Images générées à part des originaux, clé déterministe (original et réglages du profil), écriture atomique.
- `ImageResponseFactory` : image générée, original ou image de remplacement ; cache navigateur et `304`.
- Route facultative pour les images d'un dossier source, protégée contre la sortie du dossier.
- Fonction Twig `card_image_url()`, service `ImageUrlGenerator` et résolveurs d'adresse (`ImageUrlResolverInterface`).
- Commande `dsfr-card-image:purge`.
