<?php

require_once "classes/database.php";
require_once "classes/authentication.php";

class User {
    public $id;
    public $name;
    public $password;
    public $realName;
    public $biography;
    public $email;
    public $avatarPath;
    public $private;
    public $viewingRights;
    public $theme;
    public $following;
    public $followers;
    public $likeNotifications;
    public $commentNotifications;
    public $followNotifications;
    public $followRequests;
    public $isAdmin;
    public $isLocked;
    public $isDeleted;
    public $createdAt;

    function __construct($userId = null) {
        # Sets user when the id given
        if($userId != null) {
            $this->getById($userId);
        }
    }

    private function getByAny($stmt, $values){
        # Returns the user by name
        $stmt->execute($values);

        $result = $stmt->fetchAll();
        if(count($result) == 0) {
            return null;
        }

        # Set values and return a success
        $this->id = $result[0]['id'];
        $this->name = $result[0]['username'];
        $this->password = $result[0]['password'];
        $this->realName = $result[0]['real_name'];
        $this->biography = $result[0]['biography'];
        $this->email = $result[0]['email'];
        $this->avatarPath = $result[0]['avatar_path'];
        $this->private = $result[0]['private'];
        $this->theme = $result[0]['theme'];
        $this->following = $result[0]['following'];
        $this->followers = $result[0]['followers'];
        $this->likeNotifications = $result[0]['like_notifications'];
        $this->commentNotifications = $result[0]['comment_notifications'];
        $this->followNotifications = $result[0]['follow_notifications'];
        $this->followRequests = $result[0]['follow_requests'];
        $this->createdAt = $result[0]['created_at'];
        $this->isAdmin = $result[0]['is_admin'];
        $this->isLocked = $result[0]['is_locked'];
        $this->isDeleted = $result[0]['is_deleted'];

        # Check for viewing rights from
        $this->viewingRights = true;
        if(isset($_SESSION['user'])) {
            if(!$this->isFollowedBy($_SESSION['user']) && $_SESSION['user']->id != $this->id) {
                if($this->private) {
                    $this->viewingRights = false;
                }
            }
        }

        return true;
    }

    function getByName($name){
        # Returns the user by username
        $query = "SELECT * FROM users WHERE username = ?";
        $stmt = $GLOBALS['conn']->prepare($query);
        return $this->getByAny($stmt, array($name));
    }

    function getById($id){
        # Returns the user by id
        $query = "SELECT * FROM users WHERE id = ?";
        $stmt = $GLOBALS['conn']->prepare($query);
        return $this->getByAny($stmt, array($id));
    }

    function create(){
        # Create account in the database
        $return = $this->getByName($this->name);
        if($return != null) {
            return false;
        }

        $query = "INSERT INTO users (`username`, `password`, `real_name`, `email`) VALUES (?, ?, ?, ?)";

        $stmt = $GLOBALS['conn']->prepare($query);
        $stmt->execute([$this->name, password_hash($this->password, PASSWORD_DEFAULT), $this->name, $this->email]);

        return true;
    }

    function update() {
        # Update the user in the database with values from this object
        $query = "UPDATE `users` SET 
            `real_name` = ?, 
            `password` = ?, 
            `email` = ?, 
            `biography` = ?, 
            `avatar_path` = ?, 
            `private` = ?,
            `theme` = ?,
            `like_notifications` = ?,
            `comment_notifications` = ?,
            `follow_notifications` = ?,
            `follow_requests` = ?
            WHERE `id` = ?";
        $stmt = $GLOBALS['conn']->prepare($query);
        $stmt->execute([$this->realName, $this->password, $this->email, $this->biography, $this->avatarPath, $this->private ? 1 : 0, $this->theme, $this->likeNotifications ? 1 : 0, $this->commentNotifications ? 1 : 0, $this->followNotifications ? 1 : 0, $this->followRequests ? 1 : 0, $this->id]);
    }

    function login($username, $rawPassword){
        # Checks credentials and load in the user information
        $success = $this->getByName($username);
        if($success == null) {
            return null;
        }

        if ($this->isDeleted) {
            return null;
        }

        if(!password_verify($rawPassword, $this->password)) {
            return null;
        }

        # Passes the user information to the authentication and then return
        Authentication::userLogins($this);

        return $this;
    }

    function isFollowedBy($user) {
        # Returns a boolean if the user is following this user
        $query = "SELECT * FROM users_follows WHERE follower_id = ? AND user_id = ?";

        $stmt = $GLOBALS['conn']->prepare($query);
        $stmt->execute([$user->id, $this->id]);

        $result = $stmt->fetchAll();
        return count($result) != 0;
    }

    function userFollowedByStatus($user, $status) {
        # Sets the following status for the user
        $dbStatus = $this->isFollowedBy($user);
        if($dbStatus == $status) {
            return true;
        }

        $increment = 0;
        if($status) {
            $query = "INSERT INTO users_follows (follower_id, user_id) VALUES (?, ?)";
            $increment++;
        } else {
            $query = "DELETE FROM users_follows WHERE follower_id = ? AND user_id = ?";
            $increment--;
        }

        $stmt = $GLOBALS['conn']->prepare($query);
        $stmt->execute([$user->id, $this->id]);

        # Edited the following and followers amount on both the users accounts
        $query = "UPDATE `users` SET `following` = `following` + ? WHERE `id` = ?;";
        $stmt = $GLOBALS['conn']->prepare($query);
        $stmt->execute([$increment, $user->id]);

        $query = "UPDATE `users` SET `followers` = `followers` + ? WHERE `id` = ?;";
        $stmt = $GLOBALS['conn']->prepare($query);
        $stmt->execute([$increment, $this->id]);

        return true;
    }

    function howManyNotifications() {
        # count unseen notifications
        $qry = "SELECT count(*) FROM notifications WHERE user_id = ? AND seen = ?";
        
        $stmt = $GLOBALS["conn"]->prepare($qry);
        $stmt->execute([$this->id, 0]);
        return $stmt->fetchColumn();
    }

    function checkExistsFollowRequest($user) {
        # count follow request
        $qry = "SELECT count(*) FROM notifications WHERE user_id = ? AND about_user_id = ? AND `type` = ?;";
        
        $stmt = $GLOBALS["conn"]->prepare($qry);
        $stmt->execute([$this->id, $user->id, 2]);
        return $stmt->fetchColumn() != 0;
    }

    function setLocked() {
        # update locked status
        $query = "UPDATE users SET is_locked = ? WHERE id = ?";

        $stmt = $GLOBALS['conn']->prepare($query);
        $stmt->execute([$this->isLocked, $this->id]);
    }

    function setDeleted() {
        # update locked status
        $query = "UPDATE users SET is_deleted = ? WHERE id = ?";

        $stmt = $GLOBALS['conn']->prepare($query);
        $stmt->execute([$this->isDeleted, $this->id]);
    }

    function setBlocked($blockedStatus) {
        # update blocked status

        if ($blockedStatus == true) {
            $query = "INSERT INTO users_blocked (`user_id`, `blocked_user_id`) VALUES (?, ?);";
        } else {
            $query = "DELETE FROM users_blocked WHERE user_id = ? AND blocked_user_id = ?";
        }

        $stmt = $GLOBALS['conn']->prepare($query);
        $stmt->execute([$_SESSION['user']->id, $this->id]);
    }

    function checkBlocked($aboutUser) {
        # checks if user is blocked
        $query = "SELECT COUNT(*) FROM users_blocked WHERE user_id = ? AND blocked_user_id = ?";

        $stmt = $GLOBALS['conn']->prepare($query);
        $stmt->execute([$aboutUser->id, $this->id]);

        return $stmt->fetchColumn() != 0;
    }
}
