<?php

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in first.');
        redirect('login.php');
    }
}

function require_role(string $requiredRole): void
{
    require_login();
    if (get_user_role() !== $requiredRole) {
        set_flash('error', 'You are not authorized for this page.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_role('admin');
}

function require_teacher(): void
{
    require_role('teacher');
}

function require_student(): void
{
    require_role('student');
}
