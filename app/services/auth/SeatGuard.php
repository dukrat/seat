<?php
/*
The MIT License (MIT)

Copyright (c) 2014 eve-seat

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

namespace App\Services\Auth;

/*
|--------------------------------------------------------------------------
| The SeAT 'cushion' Authentication Driver
|--------------------------------------------------------------------------
|
| This driver extends the base Laravel 'Auth\'.
|
| Most of the methods here are used to manipulate groups within SeAT.
| Groups and Users have a relationship defined via a Pivot table
|
*/

class SeatGuard extends \Illuminate\Auth\Guard
{

    /*
    |--------------------------------------------------------------------------
    | useUser()
    |--------------------------------------------------------------------------
    |
    | By default, SeatGuard assumes the current user as the context if the
    | user we are working with. This mutator method allows us to shift
    | that context
    |
    */
    public function useUser($user)
    {

        $group = \Group::where('name', '=', $group_name)->first();

        if($group)
            return $group;
        else
            return false;
    }

    /*
    |--------------------------------------------------------------------------
    | findGroupByName()
    |--------------------------------------------------------------------------
    |
    | Accepts a string to return a group opject with that name
    |
    */
    public function findGroupByName($group_name)
    {

        $group = \Group::where('name', '=', $group_name)->first();

        if($group)
            return $group;
        else
            return false;
    }

    /*
    |--------------------------------------------------------------------------
    | findGroupByID()
    |--------------------------------------------------------------------------
    |
    | Accepts a integer to return a group opject with that name
    |
    */
    public function findGroupByID($group_id)
    {

        $group = \Group::where('id', '=', $group_id)->first();

        if($group)
            return $group;
        else
            return false;
    }

    /*
    |--------------------------------------------------------------------------
    | createGroup()
    |--------------------------------------------------------------------------
    |
    | Creates a new group based on the array of information provided by the
    | argument with its allocated permissions.
    |
    | A sample information argument array is:
    |
    | array(
    |   'name' => 'Permission Name',
    |   'permissions' => array(
    |       'permission_identifier' => 1
    |   )
    | )
    |
    */
    public function createGroup($info)
    {

        // Create a new group instance
        $group = new \Group();
        $group->name = $info['name'];

        // If permissions are defined, add that to the group
        if(isset($info['permissions']))
            $group->permissions = serialize($info['permissions']);

        // .. and save
        $group->save();

        return $group;
    }

    /*
    |--------------------------------------------------------------------------
    | deleteGroup()
    |--------------------------------------------------------------------------
    |
    | Deletes a group based on the Group Object argument
    |
    */
    public function deleteGroup($group)
    {

        // Clean up any users that had this group assigned
        $pivots = \GroupUserPivot::where('id', '=', $group->id)->delete();

        // Delete the actual group
        $group->delete();

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | addUserToGroup()
    |--------------------------------------------------------------------------
    |
    | Adds a user to a group if they do not already have membership.
    | Accepts a User and Group object as arguments
    |
    */
    public function addUserToGroup($user, $group)
    {

        // Check if the user is part of the group
        $check = \GroupUserPivot::where('user_id', '=', $user->id)
            ->where('group_id', '=', $group->id)->first();

        // Add the memnership if needed
        if(!$check) {

            $user_group = new \GroupUserPivot;
            $user_group->user_id = $user->id;
            $user_group->group_id = $group->id;
            $user_group->save();
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | removeUserFromGroup()
    |--------------------------------------------------------------------------
    |
    | Removes a user from a group. Accepts a User and Group object as arguments
    |
    */
    public function removeUserFromGroup($user, $group)
    {

        \GroupUserPivot::where('user_id', '=', $user->id)
            ->where('group_id', '=', $group->id)
            ->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | isSuperUser()
    |--------------------------------------------------------------------------
    |
    | Checks if a User is a SuperUser
    |
    */
    public function isSuperUser($user)
    {

        // Get the groups the user is currently a member of
        $groups = $user->groups;

        // Loop over the groups, and check if any of them
        // have the 'superuser' permission
        foreach($groups as $group) {

            $permissions = unserialize($group->permissions);

            if(array_key_exists('superuser', $permissions)) {

                if($permissions['superuser'] == 1)

                    // A group that this user belongs to with
                    // the superuser permission exists
                    return true;
            }
        }

        // No group was found having the superuser permission
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | hasAccess()
    |--------------------------------------------------------------------------
    |
    | Does a lookup to check if a user has access to a certain permission.
    | SuperUsers automatically inherit all permissions.
    |
    */
    public function hasAccess($user, $permission)
    {

        // Check if the user has superuser permissions. If so,
        // we can just return without any checks
        if($this->isSuperUser($user))
            return true;

        // Grab the groups the user is a member of
        $groups = $user->groups;

        // Start a empty permissions array that will be checked
        // for the required permission
        $permissions = array();

        // Populate the permissions from the groups into the
        // permissions array. Keep in mind that this will
        // merge and keep the unique key, meaning we
        // dont have to go and try to get this
        // unique later ;)
        foreach($groups as $group)
            $permissions = array_merge($permissions, unserialize($group->permissions));

        // Check if the permission exists in the newly created
        // array or if the superuser permission was found
        if(array_key_exists($permission, $permissions) OR array_key_exists('superuser', $permissions)) {

            // Check that the permission is actually set to 1
            // for enabled and return
            if($permissions[$permission] == 1)
                return true;

        }

        // It does not seem like the user has this permission, so
        // we will return false
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | findAllGroups()
    |--------------------------------------------------------------------------
    |
    | Returns all groups
    |
    */
    public function findAllGroups()
    {

        $groups = \Group::all();
        return $groups;
    }

    /*
    |--------------------------------------------------------------------------
    | getUserGroups()
    |--------------------------------------------------------------------------
    |
    | Returns all the groups a user is a member of
    |
    */
    public function getUserGroups($user)
    {

        // Grab the users groups
        $groups = \GroupUserPivot::where('user_id', $user->id)->get();

        // Resolve the group ids and names into an array to return
        $resolved_groups = array();
        foreach ($groups as $group)
            array_push($resolved_groups, \Group::find($group->group_id));

        // Return the array we have created
        return $resolved_groups;
    }

    /*
    |--------------------------------------------------------------------------
    | findUserById()
    |--------------------------------------------------------------------------
    |
    | Finds a user based on a ID
    |
    */
    public function findUserById($userID)
    {

        return \User::findOrFail($userID);
    }

    /*
    |--------------------------------------------------------------------------
    | getPermissions()
    |--------------------------------------------------------------------------
    |
    | Returns the permissions a group has.
    |
    | TODO: Rename this to getGroupPermissions
    |
    */
    public function getPermissions($group)
    {
        $permission_array = array();

        foreach(unserialize($group->permissions) as $key => $value) {

            if($value == 1)
                $permission_array[$key] = $value;

        }

        return $permission_array;
    }

    /*
    |--------------------------------------------------------------------------
    | findAllUsersInGroup()
    |--------------------------------------------------------------------------
    |
    | Returns all users that are memners of a certain group
    |
    */
    public function findAllUsersInGroup($group)
    {

        // Get the user_ids that have membership to the group
        $pivot_info = \GroupUserPivot::where('group_id', '=', $group->id)->get();

        // Populate a array with the User objects that are in
        // the group
        $users = array();
        foreach($pivot_info as $pivot) {

            // Find a user in the pivot with the id
            $user = \User::find($pivot->user_id);

            // If we found a user, add it to the array
            if($user)
                array_push($users, \User::find($pivot->user_id));
        }

        return $users;
    }

}
