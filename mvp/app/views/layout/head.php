<?php /** <head> compartilhado — recebe $title. */ ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'PeopleFlow') ?> · PeopleFlow</title>
  <meta name="robots" content="noindex">
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
  <script>if(localStorage.getItem('pf-theme')==='dark'||(!localStorage.getItem('pf-theme')&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')</script>
  <link rel="stylesheet" href="assets/vendor/fontawesome.min.css">
  <link rel="stylesheet" href="assets/css/peopleflow.css">
  <script src="assets/vendor/tailwind.js"></script>
  <style type="text/tailwindcss">
    @custom-variant dark (&:where(.dark, .dark *));
    @theme { --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif; }
  </style>
  <script defer src="assets/vendor/alpine.min.js"></script>
  <script type="module" src="assets/js/app.js"></script>
</head>
