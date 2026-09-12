<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8"/>
    </head>
    <body>
        <?php
        if(isset($_POST['nusername'])){
            //In reality, this will do SQL stuff
            //idk we dont have yet just run with it.
            $_SESSION['nusername'] = $_POST['nusername'];
            header('Location: front_page.php');
        }
        else{
            //error handling
        }

        ?>
        <form action='info.php' method='POST'>
            Username:<input type="text" name="nusername"><br>
            <input type="submit" value="Submit">
        <\form>
    </body>
</html>


