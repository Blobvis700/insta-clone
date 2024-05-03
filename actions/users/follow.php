<?php

# Includes
$jsonResponse = true;
require_once 'includes/header.php';
require_once 'classes/user.php';
require_once 'classes/action.php';

# Verifies user id
$user = new User();
$return = $user->getByName($GLOBALS['username']);
if($return == null) {
    Action::response(array(
        'error' => 'User not found!'
    ), 404);
}

# GET method returns liked status
if($_SERVER['REQUEST_METHOD'] == 'GET') {
    Action::response(array(
        'status' => $user->isFollowedBy($_SESSION['user'])
    ));
}

# POST method sets the follow status
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = Action::requestData();
    $followingStatus = isset($data['following']) ? boolval($data['following']) : '';
    if(!is_bool($followingStatus)){
        Action::response(array(
            'error' => 'Invalid following status given',
        ), 400);
    }

    # Checks if the user is not following him self
    if ($user->id == $_SESSION['user']->id) {
        Action::response(array(
            'error' => "Cant follow you're self!",
        ), 400);
    }

    # Save new following status
    $user->userFollowedByStatus($_SESSION['user'], $followingStatus);
    Action::response(array(
        'status' => $followingStatus
    ));
}

Action::response(array(
    'error' => 'invalid method!'
), 400);