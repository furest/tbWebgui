<?php
    session_start();
    include("../../includes/config.php");
    include("../../includes/functions.php");
?>
    <p></p>
    <h4>Create or join lab</h4>
    <form id="actionForm">
        <?php CSRFToken() ?>
        <a href="#" class="btn btn-outline btn-primary btn-lg btn-block actionBtn" id="createBtn">Create Lab</a>
        <a href="#" class="btn btn-outline btn-primary btn-lg btn-block actionBtn" id="joinBtn">Join Lab</a>
        <style>
            .actionBtn{
                padding: 1.5% 0;
            }
        </style> 
    </form>
