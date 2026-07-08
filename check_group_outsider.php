<?php
// Check what outsider role race_day_user gets on race_team groups
$all = \Drupal::entityTypeManager()->getStorage('group_role')->loadMultiple();
foreach ($all as $role) {
  if ($role->getGroupTypeId() !== 'race_team') continue;
  print $role->id() . ' (scope:' . $role->getScope() . ', global_role:' . ($role->getGlobalRole() ?? 'none') . ', admin:' . ($role->isAdmin() ? 'Y' : 'N') . ")\n";
  foreach ($role->getPermissions() as $p) {
    print "  - $p\n";
  }
}
