<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarningStoreRequest;
use App\Player;
use App\Warning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlayerWarningController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param Player $player
     * @param WarningStoreRequest $request
     * @return RedirectResponse
     */
    public function store(Player $player, WarningStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

		$msg = trim($data["message"]);

		if (Str::contains($msg, "This warning was generated automatically") || $msg === "I removed this players ban.") {
			return back()->with('error', 'Something went wrong.');
		}

        $isSenior = $this->isSeniorStaff($request);

        if (!$isSenior && $data['warning_type'] === Warning::TypeHidden) {
            abort(401);
        }

        $player->warnings()->create(array_merge($data, [
            'issuer_id' => user()->user_id,
        ]));

        return back()->with('success', 'Warning/Note has been added successfully.');
    }

    /**
     * Updates the specified resource.
     *
     * @param Player $player
     * @param Warning $warning
     * @param WarningStoreRequest $request
     * @return RedirectResponse
     */
    public function update(Player $player, Warning $warning, WarningStoreRequest $request): RedirectResponse
    {
        if (!$warning->can_be_deleted) {
            abort(401);
        }

        $staffIdentifier = license();
        $issuer = $warning->issuer()->first();

        if (!$issuer || $staffIdentifier !== $issuer->license_identifier) {
            return back()->with('error', 'You can only edit your own warnings/notes!');
        }

        $warning->update($request->validated());

        return back()->with('success', 'Successfully updated warning/note');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @param Player $player
     * @param Warning $warning
     * @return RedirectResponse
     */
    public function destroy(Request $request, Player $player, Warning $warning): RedirectResponse
    {
        $isSenior = $this->isSeniorStaff($request);

        if (!$warning->can_be_deleted && !$isSenior) {
            abort(401);
        }

        $warning->forceDelete();

        return back()->with('success', 'The warning/note has successfully been deleted from the player\'s record.');
    }

}
