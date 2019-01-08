DUPLICATE IMAGES
================

The duplicate images module allows you to find and remove duplicate images and
other documents on the public or private file system.


Problem
-------
If editors want to add images or documents to their content, they can do so by
uploading these from their local computer. Unfortunately, a default Drupal
installation does not offer any way to reuse these images, so if an editor wants
to reuse one, it will have to re-upload it. Even at that moment Drupal does not
allow you to decide what to do: reuse the existing file, overwrite it, or create
a new one. Drupal will always create a new one by appending an underscore and a
number to the filename part of the file to make it unique. This leads to
duplicate files. not a real problem, but in turn this may lead to bloated
file listings, longer backup times, larger backup sizes, "incorrect" usage
stats, unnecessary image derivative creation while an equal image derivative
could have been served directly.


Solution
--------
- To prevent duplicate images, use the module FileField Sources (1) which allows
  you to reuse already uploaded files, either managed or unmanaged.
- But if your site already has a (large) number of duplicates and you want to
  get rid of those, use this module. It will: find duplicates, find its usages,
  correct these usages and then delete the duplicates.


Warning
-------
Although this module has been tested and found to be working correctly, on a
number of our own sites, it may fail in your situation, e.g. due to:
- Contrib modules not used by us that store references to either the managed
  files or the files directly. (media?)
- Low and unalterable value of max_execution_time.
- Low and unalterable value of max_input_vars.
- Weird file or directory permissions.
- Missing permissions. All tests were done using the administrator role, and
  afaik, permissions are for the UI, not for calling API functions but I am not
  100% sure about that.

So be sure to:
- Make a backup of your database before you start.
- Make a backup of your public and/or private filesystem before you start.
- Check your site afterwards, especially for 404's on images and documents.


What does this module do?
-------------------------
In a multi step form (like update.php) this module executes the following steps:

1) Search for duplicates
- Searches are done on the public and/or private file system
- It searches for file names ('filename' as used by pathinfo (2)) that end with
  _{n} and whose part before that also exists as separate file name.
- It compares file size and md5 hash (see md5_file (3)) to determine if these
  are real duplicates or possible duplicates.
- The results are presented as a list whereby for the possible duplicates
  clickable thumbnails are shown so you can visually compare them. For documents
  just a clickable icon is shown.

2) Search for usages
- Look if a managed file record is defined for the duplicate (and the original).
- Searches for references to the managed file in user pictures, all image and
  file fields, all fields that according to their field schema have a foreign
  key to the file_managed table.
- Searches for URI references to the file or a(n image style) derivative in
  selected text and link fields.

3) Update usages
- Found references to the managed file record are updated to refer to the
  managed file record of the original (or if that does not yet exist, the uri
  field is simply updated).
- Found textual usages are changed to refer to the URI of the original document.
- Note 1: this phase uses the entity_save() function of the entity api (contrib
  and thus a dependency) to ensure that caches are cleared, hooks are called,
  file_usage is updated, rules are executed, etc.
- Note 2: this phase does keep track of failed updates so that the next phase
  can skip managed file records or files that are still being referred to.

4) Delete duplicates
- All managed file records that are no longer referred to are deleted.
- All duplicate files that ar no longer referred to are deleted.


Support for other contrib modules
---------------------------------
This module does work with:
- File, Image (Drupal core): managed files, image and file fields, and image
  styles are both handled by this module.
- Colorbox: to display thumbs for suspicious images.
- imce: does not store additional references as it does not have own tables for
  that.
- Insert: links to images inserted into body fields (even if referring to a
  derivative image) are found and corrected.
- Link: link fields are searched for references to duplicate files.
- Media Wysiswyg: this module can insert tags into (long) tex fields that are
  expanded to (derived) images. These taqs refer to managed files by their id
  and are found and corrected.

Not tested and possibly leading to missing usages:
- Other Media modules, File entity, ...?


Author
------
Erwin Derksen - aka fietserwin (4) - of Buro RaDer (5).


Links
-----
(1) https://www.drupal.org/project/filefield_sources
(2) http://cl1.php.net/manual/en/function.pathinfo.php
(3) http://cl1.php.net/manual/en/function.md5-file.php
(4) https://www.drupal.org/u/fietserwin
(5) http://www.burorader.com/
