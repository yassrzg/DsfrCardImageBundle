<?php

declare(strict_types=1);

namespace Yassrzg\DsfrCardImageBundle\Profile;

/**
 * Adaptation de l'image au cadre du profil. Mêmes notions que la propriété CSS object-fit, pour que l'image générée
 * corresponde à ce que le navigateur affiche.
 */
enum ResizeMode: string
{
    /** Recadrage centré aux proportions exactes du cadre, puis réduction : le cadre est entièrement rempli. */
    case COVER = 'cover';
    /** Réduction de l'image entière dans le cadre, proportions d'origine conservées : rien n'est coupé. */
    case CONTAIN = 'contain';
}
