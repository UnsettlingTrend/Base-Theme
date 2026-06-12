# Race Day

A Drupal 11 module for managing relay races with live GPS tracking.

---

## Features

- **Races** — Create relay race events with date, status, and route map data
- **Legs** — Define individual segments with distance, difficulty, elevation, and GPS coordinates
- **Runner Assignments** — Assign runners (Drupal users) to specific legs
- **Live GPS Tracking** — Runners submit location updates via REST API; positions displayed on race map
- **Location History** — GPS pings logged to `race_day_location_log` for replay and analysis

---

## Architecture

```
Race (event)
  ├── RaceLeg (segment 1, 2, 3...)
  │     └── start/end coordinates, route geometry, distance, difficulty
  └── RaceAssignment (runner ↔ leg)
        ├── runner (user reference)
        ├── live GPS: current_lat, current_lng, speed
        └── timing: started_at, finished_at
```

---

## Entity Types

| Entity | Machine Name | Admin Path |
|---|---|---|
| Race | `race` | `/admin/race-day/races` |
| Race Leg | `race_leg` | `/admin/race-day/legs` |
| Runner Assignment | `race_assignment` | `/admin/race-day/assignments` |

---

## Permissions

| Permission | Description |
|---|---|
| `administer race_day` | Full admin access (create, edit, delete everything) |
| `manage race_day races` | Create, edit, delete races and legs |
| `view race_day content` | View races, legs, assignments |
| `update race_day location` | Submit GPS location updates |

---

## GPS Location API

**POST** `/api/race-day/location`

```json
{
  "assignment_id": 123,
  "lat": 39.7392,
  "lng": -104.9903,
  "speed": 6.5
}
```

Requires the `update race_day location` permission and a valid session.
The endpoint verifies the logged-in user matches the assigned runner.

---

## Installation

```bash
drush en race_day -y
drush cr
```

---

## Settings

**Admin → Configuration → Content authoring → Race Day Settings**
(`/admin/config/content/race-day`)

| Setting | Default | Description |
|---|---|---|
| Location update interval | 10s | How often runners submit GPS pings |
| Map tile provider | OpenStreetMap | Tile URL template for map rendering |
| Allow public registration | No | Let authenticated users opt into public race participation |

---

## Uninstalling

```bash
drush pmu race_day -y
```

Removes all entity tables, the location log table, and state variables.
