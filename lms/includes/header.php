<?php
// includes/header.php — Shared HTML <head>
// Variables expected FROM the calling page:
//   $page_title   — e.g. "Dashboard"
//   $active_page  — e.g. "dashboard"
//   $template_path — relative path to startbootstrap-sb-admin-2-gh-pages/
//   $assets_path  — relative path to lms/assets/
// These are set by each page before calling include.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Library Management System">
    <title><?= htmlspecialchars($page_title ?? 'Library') ?> | School Library</title>

    <!-- SB Admin 2 vendor assets -->
    <link href="<?= $template_path ?>vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="<?= $template_path ?>css/sb-admin-2.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="<?= $template_path ?>vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">

    <!-- Custom LMS Styles -->
    <link href="<?= $assets_path ?>css/lms.css" rel="stylesheet">
</head>
<body id="page-top">
<div id="wrapper">
