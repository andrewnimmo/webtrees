<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2019 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace Fisharebest\Webtrees\Module;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use stdClass;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class FamilyTreeFavoritesModule
 */
class FamilyTreeFavoritesModule extends AbstractModule implements ModuleBlockInterface
{
    use ModuleBlockTrait;

    /**
     * How should this module be labelled on tabs, menus, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module */
        return I18N::translate('Favorites');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the “Favorites” module */
        return I18N::translate('Display and manage a family tree’s favorite pages.');
    }

    /**
     * Generate the HTML content of this block.
     *
     * @param Tree     $tree
     * @param int      $block_id
     * @param string   $ctype
     * @param string[] $cfg
     *
     * @return string
     */
    public function getBlock(Tree $tree, int $block_id, string $ctype = '', array $cfg = []): string
    {
        $content = view('modules/gedcom_favorites/favorites', [
            'block_id'   => $block_id,
            'favorites'  => $this->getFavorites($tree),
            'is_manager' => Auth::isManager($tree),
            'tree'       => $tree,
        ]);

        if ($ctype !== '') {
            return view('modules/block-template', [
                'block'      => str_replace('_', '-', $this->name()),
                'id'         => $block_id,
                'config_url' => '',
                'title'      => $this->title(),
                'content'    => $content,
            ]);
        }

        return $content;
    }

    /**
     * Should this block load asynchronously using AJAX?
     * Simple blocks are faster in-line, more comples ones
     * can be loaded later.
     *
     * @return bool
     */
    public function loadAjax(): bool
    {
        return false;
    }

    /**
     * Can this block be shown on the user’s home page?
     *
     * @return bool
     */
    public function isUserBlock(): bool
    {
        return false;
    }

    /**
     * Can this block be shown on the tree’s home page?
     *
     * @return bool
     */
    public function isTreeBlock(): bool
    {
        return true;
    }

    /**
     * Update the configuration for a block.
     *
     * @param Request $request
     * @param int     $block_id
     *
     * @return void
     */
    public function saveBlockConfiguration(Request $request, int $block_id)
    {
    }

    /**
     * An HTML form to edit block settings
     *
     * @param Tree $tree
     * @param int  $block_id
     *
     * @return void
     */
    public function editBlockConfiguration(Tree $tree, int $block_id)
    {
    }

    /**
     * Get favorites for a family tree
     *
     * @param Tree $tree
     *
     * @return stdClass[]
     */
    public function getFavorites(Tree $tree): array
    {
        return DB::table('favorite')
            ->where('gedcom_id', '=', $tree->id())
            ->whereNull('user_id')
            ->get()
            ->map(function (stdClass $row) use ($tree): stdClass {
                if ($row->xref !== null) {
                    $row->record = GedcomRecord::getInstance($row->xref, $tree);
                } else {
                    $row->record = null;
                }

                return $row;
            })
            ->all();
    }

    /**
     * @param Request       $request
     * @param Tree          $tree
     * @param UserInterface $user
     *
     * @return RedirectResponse
     */
    public function postAddFavoriteAction(Request $request, Tree $tree, UserInterface $user): RedirectResponse
    {
        $note         = $request->get('note', '');
        $title        = $request->get('title', '');
        $url          = $request->get('url', '');
        $xref         = $request->get('xref', '');
        $fav_category = $request->get('fav_category', '');

        $record = GedcomRecord::getInstance($xref, $tree);

        if (Auth::isManager($tree, $user)) {
            if ($fav_category === 'url' && $url !== '') {
                $this->addUrlFavorite($tree, $url, $title ?: $url, $note);
            }

            if ($fav_category === 'record' && $record instanceof GedcomRecord && $record->canShow()) {
                $this->addRecordFavorite($tree, $record, $note);
            }
        }

        $url = route('tree-page', ['ged' => $tree->name()]);

        return new RedirectResponse($url);
    }

    /**
     * @param Request       $request
     * @param Tree          $tree
     * @param UserInterface $user
     *
     * @return RedirectResponse
     */
    public function postDeleteFavoriteAction(Request $request, Tree $tree, UserInterface $user): RedirectResponse
    {
        $favorite_id = (int) $request->get('favorite_id');

        if (Auth::isManager($tree, $user)) {
            DB::table('favorite')
                ->where('favorite_id', '=', $favorite_id)
                ->whereNull('user_id')
                ->delete();
        }

        $url = route('tree-page', ['ged' => $tree->name()]);

        return new RedirectResponse($url);
    }

    /**
     * @param Tree   $tree
     * @param string $url
     * @param string $title
     * @param string $note
     *
     * @return void
     */
    private function addUrlFavorite(Tree $tree, string $url, string $title, string $note)
    {
        DB::table('favorite')->updateOrInsert([
            'gedcom_id' => $tree->id(),
            'user_id'   => null,
            'url'       => $url,
        ], [
            'favorite_type' => 'URL',
            'note'          => $note,
            'title'         => $title,
        ]);
    }

    /**
     * @param Tree         $tree
     * @param GedcomRecord $record
     * @param string       $note
     *
     * @return void
     */
    private function addRecordFavorite(Tree $tree, GedcomRecord $record, string $note)
    {
        DB::table('favorite')->updateOrInsert([
            'gedcom_id' => $tree->id(),
            'user_id'   => null,
            'xref'      => $record->xref(),
        ], [
            'favorite_type' => $record::RECORD_TYPE,
            'note'          => $note,
        ]);
    }
}
