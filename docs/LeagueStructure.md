# League Structure

This document defines a standard JSON schema to represent tournament formats in `season_structure`.

## Purpose

Provide one consistent format for:

- league and playoff phases,
- qualification rules,
- pairings and team slots,
- confirmed and pending fixtures,
- standings rows in every competitive step.

## Base Structure

```json
{
  "league_id": 195,
  "provider_league_id": 239,
  "season_structure": {
    "schema_version": 1,
    "competition_type": "league|cup|hybrid",
    "current_phase": "F1",
    "notes": "Short text with global rules.",
    "classified_team_slots": {
      "F1R1": null,
      "F1R2": null,
      "F1R3": null,
      "F1R4": null,
      "F1R5": null,
      "F1R6": null,
      "F1R7": null,
      "F1R8": null,
      "F2A": null,
      "F2B": null,
      "F2C": null,
      "F2D": null,
      "F3A": null,
      "F3B": null,
      "F4A": null
    },
    "phases": {
      "F1": {
        "phase_id": "F1",
        "phase_name": "First phase",
        "order": 1,
        "phase_type": "group",
        "group_mode": "single_group|multi_group",
        "match_type": "single_leg|two_legs",
        "standings": [
          {
            "position": 1,
            "team": 264,
            "points": null,
            "matches_played": null,
            "matches_won": null,
            "matches_drawn": null,
            "matches_lost": null,
            "goals_for": null,
            "goals_against": null,
            "home_goals_for": null,
            "away_goals_for": null
          }
        ],
        "qualification": {
          "advances_to_phase": "F2",
          "method": "ranking_table|best_ranked|manual",
          "qualified_positions": [1, 2, 3, 4, 5, 6, 7, 8],
          "description": "Top 8 teams qualify."
        },
        "matches": [
          {
            "fixture_id": 1498269,
            "home_team_id": 264,
            "away_team_id": 265,
            "round": "Regular Season - 1",
            "fixture_date": "2026-01-16 23:20:00",
            "match_type": "two_legs"
          }
        ]
      },
      "F2": {
        "phase_id": "F2",
        "phase_name": "Quarterfinals",
        "order": 2,
        "phase_type": "playoff",
        "match_type": "two_legs",
        "seeding_method": "draw|ranking|fixed_bracket",
        "ties": [
          {
            "tie_id": "F2A",
            "name": "Quarterfinal 1",
            "teams": ["F1R1", "F1R8"],
            "winner_slot": "F2A",
            "standings": [
              {
                "position": 1,
                "team": "F1R1",
                "points": null,
                "matches_played": null,
                "matches_won": null,
                "matches_drawn": null,
                "matches_lost": null,
                "goals_for": null,
                "goals_against": null,
                "home_goals_for": null,
                "away_goals_for": null
              },
              {
                "position": 2,
                "team": "F1R8",
                "points": null,
                "matches_played": null,
                "matches_won": null,
                "matches_drawn": null,
                "matches_lost": null,
                "goals_for": null,
                "goals_against": null,
                "home_goals_for": null,
                "away_goals_for": null
              }
            ],
            "matches": [
              { "fixture_id": null, "leg": 1, "home_team_id": null, "away_team_id": null },
              { "fixture_id": null, "leg": 2, "home_team_id": null, "away_team_id": null }
            ]
          }
        ]
      }
    }
  }
}
```

## Phase Types

- `group`: standings table (single table or multiple groups).
- `playoff`: knockout ties.
- `hybrid` (competition level): league/group phase + knockout phases.

## Standings Rules

- `single_group`: add `phase.standings` with one row per team in that group.
- `multi_group`: add `group.standings` in each item inside `phase.groups`.
- `playoff`: add `tie.standings` in every tie (normally 2 rows, one per team/slot).
- If results are not available yet, create all standings rows anyway with stat fields in `null`.

Standard standings row:

```json
{
  "position": 1,
  "team": 264,
  "points": null,
  "matches_played": null,
  "matches_won": null,
  "matches_drawn": null,
  "matches_lost": null,
  "goals_for": null,
  "goals_against": null,
  "home_goals_for": null,
  "away_goals_for": null
}
```

## Classification Slots And Progression

Use `classified_team_slots` to keep deterministic slots for winners/qualifiers across phases.

Guidelines:

- Slots are defined at `season_structure.classified_team_slots` (global pool).
- `qualification.output_slots` in group phases maps table positions into slots.
- Each playoff tie should include `winner_slot` to record the winner into a slot.
- Next phase `teams` should reference the previous phase slots (e.g. `"teams": ["F2A", "F2B"]`).

Example progression:

- F1 (group) outputs `F1R1..F1R8`
- F2 (playoff) ties write winners into `F2A..F2D`
- F3 ties use `F2A..F2D` as inputs and write winners into `F3A..F3B`
- F4 final uses `F3A` vs `F3B`

This keeps pairing stable and allows the sync service to fill teams and results incrementally.

## Match Phases And Round Labels

When using existing fixtures, keep round names consistent with source data.
If round labels differ from phase names, document it in `notes` and keep a mapping in code.

Example for UCL:

- Phase name: "Octavos de final"
- Round label in fixtures: "Round of 16" (or older: "Round of 32")

## Data Needed To Generate A New Tournament JSON

When requesting a new JSON for a league/season, provide:

1) League identity:
   - `league_id` (local)
   - `provider_league_id`
   - `season` (year)
2) Competition rules:
   - Phases and order (F1, F2, F3...)
   - Phase types (group vs playoff)
   - Match type per phase (single_leg or two_legs)
   - Qualification rules (positions or manual)
   - Seeding rules and bracket rules (if any)
3) Known fixtures (optional but ideal):
   - `fixture_id`, `home_team_id`, `away_team_id`, `round`, `fixture_date`
4) Expected slot mapping:
   - Output slots for group phases
   - Winner slots for playoff ties
   - How each phase feeds into the next

## Organization And Qualification

- `group_mode`:
  - `single_group`: one standings table.
  - `multi_group`: multiple groups.
- `match_type`:
  - `single_leg`: one match per tie.
  - `two_legs`: home and away.
- `seeding_method`:
  - `draw`: random draw.
  - `ranking`: seed by table position/points.
  - `fixed_bracket`: predefined bracket mapping.
- `qualification.method`:
  - `ranking_table`: qualify by positions.
  - `best_ranked`: qualify best ranked teams across groups/paths.
  - `manual`: qualification defined outside the system.

## Fail-Safe Prompt Template

Use this prompt to generate a league structure with minimal ambiguity:

```text
Generate a valid and consistent season_structure JSON for:
- league_id: <int>
- provider_league_id: <int>
- league_name: <string>
- season: <int>

Mandatory rules:
1) Use schema_version=1.
2) Return phases as an object (not an array), keyed by F1, F2, F3...
3) Include current_phase in season_structure.
4) Every phase must include: phase_id, phase_name, order, phase_type, match_type.
5) Always include season_structure.classified_team_slots.
6) If phase_type=group: include group_mode, qualification, matches, and standings.
7) If phase_type=playoff: include seeding_method, ties, matches per leg, standings per tie, and winner_slot for each tie.
8) If teams/fixtures are unknown, use null (do not invent IDs).
9) If home/away leg rules depend on ranking, write them in notes/description.
10) Keep stable naming and ordering (F2A, F2B, F3A...).
11) For multi_group, each group must include its own standings rows.
12) Return only valid JSON.

Tournament context:
<paste exact competition rules here>
```

## Prompt With Base JSON (Recommended)

For better reliability, pass a base JSON template and ask the model to edit it only.

Base file to use:

- `docs/league-structure-prompt-base.json`

Suggested prompt:

```text
You will receive:
1) A base JSON template.
2) Tournament rules/context.
3) Optional known fixtures/teams.

Task:
- Update ONLY the JSON fields needed to represent the tournament.
- Keep keys and schema style.
- Remove unused phases if they do not apply.
- Keep unknown values as null.
- Keep phases as object: F1, F2, F3...
- Set season_structure.current_phase correctly.
- Ensure standings are present in all applicable places:
  - phase.standings for single_group,
  - group.standings for multi_group,
  - tie.standings for playoff ties.
- Return JSON only.

Base JSON:
<paste docs/league-structure-prompt-base.json here>

Tournament context:
<paste official rules here>
```

## Prompt Example: UEFA Champions League

```text
Generate season_structure for UEFA Champions League 2025-2026.
Format:
- F1 single league phase with 36 teams.
- Positions 1-8 qualify directly to Round of 16.
- Positions 9-24 play a knockout playoff (two legs) for Round of 16 spots.
- From Round of 16 onward: knockout ties, two legs.
- Final is single match at neutral venue.
Include current_phase and phases as an object.
Include standings in every phase/tie (use null stats when unknown).
Use null when teams/fixtures are not known yet.
Return JSON only.
```

## Prompt Example: FIFA World Cup 2026

```text
Generate season_structure for FIFA World Cup 2026.
Format:
- F1 group stage (12 groups of 4 teams).
- Teams advancing to F2: top 2 from each group + best third-placed teams based on official rules.
- From F2 onward: Round of 32, Round of 16, Quarterfinals, Semifinals, Final.
- Knockout rounds are single-leg matches.
- Include third-place match.
Include current_phase and phases as an object.
Include standings in group phase and every knockout tie (null stats allowed).
Use null when teams/fixtures are not known yet.
Return JSON only.
```

## Reference Files

- Staging structure: `docs/league-195-structure-staging.json`
- Local structure: `docs/league-195-structure-local.json`
- Prompt base template: `docs/league-structure-prompt-base.json`
