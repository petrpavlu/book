<?php

// Copyright (C) 2018 Petr Pavlu <setup@dagobah.cz>
// SPDX-License-Identifier: MIT
//
// Tiny bookmark manager that uses an SQLite database for the storage.

header('Content-Type: text/html; charset=UTF-8');

const DATABASE = 'bookmarks.db';

abstract class DisplayPage
{
  const NORMAL = 0;
  const INSTALL = 1;
  const INSTALL_DONE = 2;
  const REDIRECT_HOME = 3;
}

// Process the normal GET request that reads all bookmarks.
function process_get_request($db, &$bookmarks, &$error) {
  $result = $db->query('SELECT id, url FROM bookmarks ORDER BY id DESC');
  if ($result === FALSE) {
    $error = 'Failed to read bookmarks: error executing the query.';
    return;
  }

  $bookmarks = array();
  while ($row = $result->fetchArray())
    $bookmarks[] = array($row['id'], $row['url']);
  return $bookmarks;
}

// Process the normal POST request that adds a new bookmark.
function process_post_request($db, &$display_page, &$error)
{
  if (!isset($_POST['url'])) {
    $error = 'No bookmark URL specified.';
    return;
  }

  $stmt = $db->prepare('INSERT INTO bookmarks (url) VALUES (:url)');
  if ($stmt === FALSE) {
    $error = 'Failed to add the new bookmark: error preparing the query.';
    return;
  }
  if ($stmt->bindValue(':url', $_POST['url'], SQLITE3_TEXT) === FALSE) {
    $error = 'Failed to add the new bookmark: error binding the url value.';
    return;
  }
  $result = $stmt->execute();
  if ($result === FALSE) {
    $error = 'Failed to add the new bookmark: error executing the query.';
    return;
  }

  $display_page = DisplayPage::REDIRECT_HOME;
}

// Process the delete POST request that deletes an existing bookmark.
function process_delete_request($db, &$display_page, &$error)
{
  if (!isset($_POST['id'])) {
    $error = 'No bookmark ID specified.';
    return;
  }

  $stmt = $db->prepare('DELETE FROM bookmarks WHERE id=:id');
  if ($stmt === FALSE) {
    $error = 'Failed to delete the bookmark: error preparing the query.';
    return;
  }
  if ($stmt->bindValue(':id', $_POST['id'], SQLITE3_INTEGER) === FALSE) {
    $error = 'Failed to delete the bookmark: error binding the id value.';
    return;
  }
  $result = $stmt->execute();
  if ($result === FALSE) {
    $error = 'Failed to delete the bookmark: error executing the query.';
    return;
  }

  $display_page = DisplayPage::REDIRECT_HOME;
}

// Process the install GET request that displays the installation page.
function process_install_request($db, &$display_page, &$error)
{
  // Check if the bookmarks table already exists.
  $res = $db->querySingle('SELECT name FROM sqlite_master WHERE '.
    'type=\'table\' AND name=\'bookmarks\';');
  if ($res === FALSE) {
    $error = 'Failed to check existence of the bookmarks table.';
    return;
  }
  if ($res !== NULL) {
    $error = 'Bookmarks table already exists.';
    return;
  }

  $display_page = DisplayPage::INSTALL;
}

// Process the install POST request that initializes the database.
function process_install2_request($db, &$display_page, &$error)
{
  $res = $db->query('CREATE TABLE bookmarks (id INTEGER PRIMARY KEY ' .
    'AUTOINCREMENT, url TEXT);');
  if ($res === FALSE) {
    $error = 'Failed to create the bookmarks table.';
    return;
  }
  $display_page = DisplayPage::INSTALL_DONE;
}

function main(&$display_page, &$bookmarks, &$error)
{
  // Open the database.
  try {
    $db = new SQLite3(DATABASE);
  } catch (Exception $e) {
    $error = 'Failed to open the bookmark database.';
    return;
  }

  // Process the request.
  switch ($_SERVER['REQUEST_METHOD']) {
  case 'GET':
    if (isset($_GET['install']))
      process_install_request($db, $display_page, $error);
    else
      process_get_request($db, $bookmarks, $error);
    break;
  case 'POST':
    if (isset($_POST['install2']))
      process_install2_request($db, $display_page, $error);
    elseif (isset($_POST['delete']))
      process_delete_request($db, $display_page, $error);
    else
      process_post_request($db, $display_page, $error);
    break;
  default:
    $error = "Unknown request '$_SERVER[REQUEST_METHOD]'.";
    break;
  }

  // Close the database.
  if ($db->close() === FALSE && $error === NULL)
    $error = 'Failed to close the bookmark database.';
}

$display_page = DisplayPage::NORMAL;
$bookmarks = array();
$error = NULL;
main($display_page, $bookmarks, $error);
if ($display_page === DisplayPage::REDIRECT_HOME) {
  header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
  exit();
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html>
  <head>
    <title>Bookmarks</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style type="text/css">
      h1 {text-align: center}
      hr {border: none; height: 1px; background-color: black}
      a {overflow-wrap: break-word}
      .url {display: flex; align-items: center}
      .url input[name="url"] {flex: 1; margin-left: 5px; margin-right: 5px}
      .inline {display: inline}
      .delete-button {border: none}
      .footer {text-align: center}
    </style>
  </head>
  <body>
    <h1>
      <a href="<?php echo htmlspecialchars(
        strtok($_SERVER['REQUEST_URI'], '?')); ?>">
        Bookmarks
      </a>
    </h1>
    <hr>
<?php if ($error !== NULL): // Error case. ?>
    <p>
      Error: <?php echo htmlspecialchars($error); ?>
    </p>
<?php elseif ($display_page === DisplayPage::INSTALL): // Install request. ?>
    <form method="post" action="">
      <div>
        <input type="hidden" name="install2">
        <input type="submit" value="Install">
      </div>
    </form>
<?php elseif ($display_page === DisplayPage::INSTALL_DONE): // Install done. ?>
    <p>Installation successful.</p>
<?php else: // Output list of bookmarks. ?>
    <form method="post" action="">
      <fieldset>
        <legend>Add new bookmark:</legend>
        <div class="url">
          URL: <input id="url-input" type="text" name="url">
          <input type="submit" value="Add entry">
          <script type="text/javascript">
            document.getElementById("url-input").focus();
          </script>
        </div>
      </fieldset>
    </form>
    <hr>
    <ul>
<?php
foreach ($bookmarks as $bookmark) {
  $id = htmlspecialchars($bookmark[0]);
  $url = htmlspecialchars($bookmark[1]);
  echo "<li>\n";
  echo "  <a href=\"$url\">$url</a>, \n";
  echo "  <form method=\"post\" action=\"\" class=\"inline\">\n";
  echo "    <div class=\"inline\">\n";
  echo "      <input type=\"hidden\" name=\"delete\">\n";
  echo "      <input type=\"hidden\" name=\"id\" value=\"$id\">\n";
  echo "      <input type=\"submit\" value=\"X\" class=\"delete-button\">\n";
  echo "    </div>\n";
  echo "  </form>\n";
  echo "</li>\n";
}
?>
    </ul>
<?php endif; ?>
    <hr>
    <p class="footer"><?php echo htmlspecialchars(date("Y-m-d H:i:s")); ?></p>
  </body>
</html>
