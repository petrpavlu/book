<?php

// Copyright (C) 2018 Petr Pavlu <setup@dagobah.cz>
// SPDX-License-Identifier: MIT
//
// Tiny bookmark manager that uses an SQLite database for the storage.

header('Content-Type: text/html; charset=UTF-8');

abstract class DisplayPage
{
  const Normal = 0;
  const Install = 1;
  const InstallDone = 2;
}

// Read bookmarks from the database.
function read_bookmarks($db, &$error)
{
  $bookmarks = array();

  $result = $db->query('SELECT rowid, * FROM bookmarks');
  if ($result === FALSE) {
    $error = 'Failed to read bookmarks: error executing query.';
    return $bookmarks;
  }

  while ($row = $result->fetchArray())
    $bookmarks[] = array($row['rowid'], $row['url']);
  return $bookmarks;
}

// Process the normal GET request that reads all bookmarks.
function process_get_request($db, &$bookmarks, &$error) {
  $bookmarks = read_bookmarks($db, $error);
}

// Process the normal POST request that adds a new bookmark.
function process_post_request($db, &$bookmarks, &$error)
{
  if (!isset($_POST['url'])) {
    $error = 'No bookmark URL specified.';
    return;
  }

  $stmt = $db->prepare('INSERT INTO bookmarks (url) VALUES (:url)');
  if ($stmt === FALSE) {
    $error = 'Failed to add new bookmark: error preparing query.';
    return;
  }
  if ($stmt->bindValue(':url', $_POST['url'], SQLITE3_TEXT) === FALSE) {
    $error = 'Failed to add new bookmark: error binding url value.';
    return;
  }
  $result = $stmt->execute();
  if ($result === FALSE) {
    $error = 'Failed to add new bookmark: error executing query.';
    return;
  }

  $bookmarks = read_bookmarks($db, $error);
}

// Process the delete POST request that deletes an existing bookmark.
function process_delete_request($db, &$bookmarks, &$error)
{
  if (!isset($_POST['id'])) {
    $error = 'No bookmark ID specified.';
    return;
  }

  $stmt = $db->prepare('DELETE FROM bookmarks WHERE rowid=:rowid');
  if ($stmt === FALSE) {
    $error = 'Failed to delete bookmark: error preparing query.';
    return;
  }
  if ($stmt->bindValue(':rowid', $_POST['id'], SQLITE3_INTEGER) === FALSE) {
    $error = 'Failed to delete bookmark: error binding rowid value.';
    return;
  }
  $result = $stmt->execute();
  if ($result === FALSE) {
    $error = 'Failed to delete bookmark: error executing query.';
    return;
  }

  $bookmarks = read_bookmarks($db, $error);
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

  $display_page = DisplayPage::Install;
}

// Process the install POST request that initializes the database.
function process_install2_request($db, &$display_page, &$error)
{
  $res = $db->query('CREATE TABLE bookmarks (url TEXT);');
  if ($res === FALSE) {
    $error = 'Failed to create the bookmarks table.';
    return;
  }
  $display_page = DisplayPage::InstallDone;
}

function main(&$display_page, &$bookmarks, &$error)
{
  // Open the database.
  try {
    $db = new SQLite3('bookmarks.db');
  } catch (Exception $e) {
    $error = 'Failed to open bookmark database.';
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
      process_delete_request($db, $bookmarks, $error);
    else
      process_post_request($db, $bookmarks, $error);
    break;
  default:
    $error = "Unknown request '" . $_SERVER['REQUEST_METHOD'] . "'.";
    break;
  }

  // Close the database.
  if ($db->close() === FALSE && $error === NULL)
    $error = 'Failed to close bookmark database.';
}

$display_page = DisplayPage::Normal;
$bookmarks = array();
$error = NULL;
main($display_page, $bookmarks, $error);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html>
  <head>
    <title>Bookmarks</title>
    <style type="text/css">
      h1 {text-align: center}
      hr {border: none; height: 1px; background-color: black}
      .inline {display: inline}
      .delete-button {border: none}
      .footer {text-align: center}
    </style>
  </head>
  <body>
    <h1>Bookmarks</h1>
    <hr>
<?php if ($error !== NULL): // Error case. ?>
    <p>
      Error: <?php echo htmlspecialchars($error); ?>
    </p>
<?php elseif ($display_page == DisplayPage::Install): // Install request. ?>
    <form method="post" action="">
      <div>
        <input type="hidden" name="install2">
        <input type="submit" value="Install">
      </div>
    </form>
<?php elseif ($display_page == DisplayPage::InstallDone): // Install done. ?>
    <p>Installation successful.</p>
<?php else: // Output list of bookmarks. ?>
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
    <form method="post" action="">
      <fieldset>
        <legend>Add new bookmark:</legend>
        <input type="hidden" name="key" value="<?php echo $key; ?>">
        URL: <input type="text" name="url">
        <input type="submit" value="Add entry">
      </fieldset>
    </form>
<?php endif; ?>
    <hr>
    <p class="footer"><?php echo htmlspecialchars(date("Y-m-d H:i:s")); ?></p>
  </body>
</html>