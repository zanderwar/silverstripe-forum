<div class="forum-header">
	<div class="forum-header-forms">

		<span class="forum-search-dropdown-icon"></span>
		<div class="forum-search-bar">
			<form class="forum-search" action="$Link('search')" method="get">
				<fieldset>
					<label for="search-text"><%t ForumHeader_ss.SEARCHBUTTON "Search" %></label>
					<input id="search-text" class="text active" type="text" name="Search" value="$Query.ATT" />
					<input class="submit action" type="submit" value="<% _t('ForumHeader_ss.SEARCHBUTTON','Search') %>"/>
				</fieldset>
			</form>
		</div>

		<form class="forum-jump" action="#">
			<label for="forum-jump-select"><%t ForumHeader_ss.JUMPTO "Jump to:" %></label>
			<select id="forum-jump-select" onchange="if(this.value) location.href = this.value">
				<option value=""><%t ForumHeader_ss.JUMPTO "Jump to:" %></option>
				<!-- option value=""><% _t('ForumHeader_ss.SELECT','Select') %></option -->
				<% if $ShowInCategories %>
					<% loop $Forums %>
						<optgroup label="$Title">
							<% loop $CategoryForums %>
								<% if $can('view') %>
									<option value="$Link">$Title</option>
								<% end_if %>
							<% end_loop %>
						</optgroup>
					<% end_loop %>
				<% else %>
					<% loop $Forums %>
						<% if $can('view') %>
							<option value="$Link">$Title</option>
						<% end_if %>
					<% end_loop %>
				<% end_if %>
			</select>
		</form>

		<% if $NumPosts %>
			<p class="forumStats">
				$NumPosts
				<strong><%t ForumHeader_ss.POSTS "Posts" %></strong>
				<%t ForumHeader_ss.IN "in" %> $NumTopics <strong><%t ForumHeader_ss.TOPICS "Topics" %></strong>
				<%t ForumHeader_ss.BY "by" %> $NumAuthors <strong><%t ForumHeader_ss.MEMBERS "members" %></strong>
			</p>
		<% end_if %>

	</div><!-- forum-header-forms. -->

	<h1 class="forum-heading"><a name='Header'>$HolderSubtitle</a></h1>
	<p class="forum-breadcrumbs">$Breadcrumbs</p>
	<p class="forum-abstract">$ForumHolder.HolderAbstract</p>

	<% if $Moderators %>
		<p>
			Moderators:
			<% loop $Moderators %>
				<a href="$Link">$Nickname</a>
				<% if not $Last %>, <% end_if %>
			<% end_loop %>
		</p>
	<% end_if %>

</div><!-- forum-header. -->
