Select '';

#Owen Zidar
#ozidar
#CSE30246
#Homework 3
#10/14/2015

##########################################
#Q1: How many games are in this dataset?
#Solution:

Select '';

Select count(*) 
as 'How many games are in this dataset?'
from cfb_game;

Select '';


##########################################
#Q2: Return the names of all of the coaches who have ever been ranked #1 in the AP poll or the BCS poll.
#HINT - Only need to use the weekly rankings, no more information is gained from season rankings table
#Solution:

Select '';

Select distinct c.coach 
as 'Coaches who have ever been ranked #1 in the AP poll or the BCS poll'
From cfb_coach c, cfb_weekly_rankings r 
Where (r.ap = '1' or r.bcs = '1') 
and c.team = r.team 
and c.opp = r.opp 
and c.season = r.season 
and c.wk = r.wk;

Select '';

##########################################
#Q3a: What team has beat the spread the most times? 
#HINT - There is no need for it in this particular query, but -0.5 points if your query doesn't handle ties.
#Tiebreaking is straightforward - google knows.
#Solution:

Select '';

Select Games.team
as 'What team has beat the spread the most times?'
From (
Select G.spread 
From (
Select g.team, count(o.spread_wl) as spread 
From cfb_game g, cfb_odds o 
Where o.spread_wl = 'W' and g.team = o.team and g.opp = o.opp and g.season = o.season and g.wk = o.wk 
Group By g.team) G 
Order By G.spread desc
Limit 1
) BiggestSpread, (
Select Game.team, count(Odds.spread_wl) as spread 
From cfb_game Game, cfb_odds Odds 
Where Odds.spread_wl = 'W' and Game.team = Odds.team and Game.opp = Odds.opp and Game.season = Odds.season and Game.wk = Odds.wk 
Group By Game.team
) Games 
Where BiggestSpread.spread = Games.spread;

Select '';

##########################################
#Q4: Which team's AP ranking changed the least through a single season; what was the season?
#Ranking varies week to week, unranked teams or teams that were unranked at some point in the season do not count.
#HINT - use stddev function, the answer turns out to have stddev = 0.
#You'll need tiebreaking here; and it'll be used here.
#Solution:

Select '';

Select A.team as Team, A.season as Season 
From (
Select team, season, stddev(ap) as min 
From cfb_weekly_rankings where (team, season) not in (
Select distinct team, season 
From cfb_weekly_rankings
Where ap = 'NR' 
)
Group by team, season
) A, 
(
Select min(stds) as x 
From (
Select team, season, stddev(ap) as stds 
From cfb_weekly_rankings where (team, season) not in (
Select distinct team, season 
From cfb_weekly_rankings 
Where ap = 'NR' 
)
Group By team, season
) B
) C
Where A.min = C.x;

Select '';

##########################################
#Q5: Who is the winningest team (in terms winning percentage) in the month of October?
#HINT - Montana only played a single game... cheaters.
#This was the most difficult query, it also is useful for other queries later on. Do this one first.
#Super HINT - don't use result
#Super HINT - Consider doing a UNION ALL instead of a self-join
#Super Super HINT - You can express a boolean in a select clause, ie, pts>opp_pts will return 1 or 0 which you can then count and sum in an outer query
#Solution:

Select '';

Select team as Team, pct as Pct
From (
Select A.team, sum(A.win)/count(A.win) as pct
From (
Select cfb_game.team, cfb_game.opp, cfb_game.season, cfb_game.wk, gday, cfb_game.pts>cfb_game.opp_pts as win
From cfb_game, cfb_gameday
Where Month(cfb_gameday.gday)=10
and cfb_gameday.team = cfb_game.team
and cfb_gameday.opp = cfb_game.opp
and cfb_gameday.season = cfb_game.season
and cfb_gameday.wk = cfb_game.wk
Union All(
Select cfb_game.opp, cfb_game.team, cfb_game.season, cfb_game.wk, gday, cfb_game.pts<cfb_game.opp_pts as win
From cfb_game, cfb_gameday
Where Month(cfb_gameday.gday)=10
and cfb_gameday.team = cfb_game.opp
and cfb_gameday.opp = cfb_game.team
and cfb_gameday.season = cfb_game.season
and cfb_gameday.wk = cfb_game.wk
)
) A
Group By A.team
Order By pct desc
Limit 1
) C;

Select '';

##########################################
#Q6a: Which conference has the most passing yards?
#Solution:

Select '';

select cfb_conf.conf
as 'Which conerence has the most passing'
from cfb_conf, cfb_offensive_stats
where cfb_conf.team = cfb_offensive_stats.team
and cfb_conf.season = cfb_offensive_stats.season
group by cfb_conf.conf
order by sum(cfb_offensive_stats.pass_yds) desc
limit 1;

Select '';

##########################################
#Q7: Which bowl game has, on average, the highest AP ranked teams playing. (Ignore not-ranked teamed)
#Solution:

Select '';

Select G.bowl 
as 'Which bowl game has, on average, the highest AP ranked teams playing?'
From (
Select F.bowl, avg(average) as total_average
From (
Select D.bowl, D.team, D.opp, D.season, D.ap_team, E.ap_opp, (D.ap_team+E.ap_opp)/2 as average
From (
Select C.bowl, C.team, C.opp, C.season, C.ap_team
From (
Select cfb_game.bowl, cfb_game.team, cfb_game.opp, cfb_game.season, cfb_weekly_rankings.ap as ap_team
From cfb_game, cfb_weekly_rankings
Where cfb_game.bowl IS NOT NULL
and cfb_game.team = cfb_weekly_rankings.team
and cfb_game.opp = cfb_weekly_rankings.opp
and cfb_game.season = cfb_weekly_rankings.season
and cfb_game.wk = cfb_weekly_rankings.wk
) C
Order by C.bowl
) D,
(
Select C.bowl, C.team, C.opp, C.season, C.ap_opp
From (
Select cfb_game.bowl, cfb_game.team, cfb_game.opp, cfb_game.season, cfb_weekly_rankings.ap as ap_opp
From cfb_game, cfb_weekly_rankings
Where cfb_game.bowl IS NOT NULL
and cfb_game.team = cfb_weekly_rankings.opp
and cfb_game.opp = cfb_weekly_rankings.team
and cfb_game.season = cfb_weekly_rankings.season
and cfb_game.wk = cfb_weekly_rankings.wk
) C
Order by C.bowl
) E
Where D.ap_team != 'NR'
and E.ap_opp != 'NR'
and D.bowl = E.bowl
and D.team = E.team
and D.opp = E.opp
and D.season = E.season
) F
Group By F.bowl
Order By total_average
Limit 1
) G;

Select '';

##########################################
#Q8a: Which AA-team(s) has beaten the most BCS teams, and how many games have they won?
#HINT: tie breaker here too - and you'll need it too.
#My solution uses ...where (G.team, G.season) in (select team, season from cfb_... a few times.
#note how you can use (x,y) in (select x,y from)
#Solution:

Select '';

Select Teams.team as Team, Teams.wins as Wins
From (
Select B.team, sum(B.pts>B.opp_pts) as wins
From (
Select A.team, A.opp, A.season, A.wk, A.pts, A.opp_pts
From (
Select cfb_game.team, cfb_game.opp, season, wk, cfb_game.pts, opp_pts
From cfb_game
Union All (
Select cfb_game.opp, cfb_game.team, season, wk, opp_pts, pts
From cfb_game
)
) A
Where (A.team,A.season) in (
Select cfb_conf.team, cfb_conf.season
From cfb_conf
Where cfb_conf.in_bcs = 'zAA'
)
and (A.opp,A.season) in (
Select cfb_conf.team, cfb_conf.season
From cfb_conf
Where cfb_conf.in_bcs = 'BCS'
)
) B
Group By B.team
Order By wins desc
Limit 1
) Wins, 
(
Select D.team, sum(D.pts>D.opp_pts) as wins
From (
Select C.team, C.opp, C.season, C.wk, C.pts, C.opp_pts
From (
Select cfb_game.team, cfb_game.opp, season, wk, cfb_game.pts, opp_pts
From cfb_game
Union All (
Select cfb_game.opp, cfb_game.team, season, wk, opp_pts, pts
From cfb_game
)
) C
Where (C.team,C.season) in (
Select cfb_conf.team, cfb_conf.season
From cfb_conf
Where cfb_conf.in_bcs = 'zAA'
)
and (C.opp,C.season) in (
Select cfb_conf.team, cfb_conf.season
From cfb_conf
Where cfb_conf.in_bcs = 'BCS'
)
) D
Group By D.team
Order By wins desc
) Teams
Where Teams.wins = Wins.wins
Order by Teams.team;

Select '';


##########################################
#9 Which coach has won at least 50 games with the fewest points How many games were won and how many points were scored.
#HINT - no need for a tiebreaker here
#I'll also accept 2308 points if you use the total points, not just points scored in the wins.
#Solution:

Select '';

Select C.coach as Coach, C.total_pts as 'Total Points', C.total_wins as Wins
From (
Select B.coach, sum(B.pts) as total_pts, sum(B.wins) as total_wins
From (
Select cfb_coach.coach, A.team, A.opp, A.season, A.wk, A.pts, A.pts>A.opp_pts as wins
From (
Select cfb_game.team, cfb_game.opp, season, wk, cfb_game.pts, opp_pts
From cfb_game
Union All (
	Select cfb_game.opp, cfb_game.team, season, wk, opp_pts, pts
	From cfb_game
)
) A, cfb_coach
Where cfb_coach.team = A.team
and cfb_coach.opp = A.opp
and cfb_coach.season = A.season
and cfb_coach.wk = A.wk
) B 
Group By B.coach
Order By B.pts desc
) C
Where C.total_wins >= 50
Order By C.total_pts
Limit 1;

Select '';

##########################################
#10 Has a team ever had more penalty yards than total yards in a game? If so, what team, season and week was it?
#Solution:

Select '';

Select cfb_offensive_stats.team as Team, cfb_offensive_stats.wk as Week, cfb_offensive_stats.season as Season
From cfb_offensive_stats, cfb_penalty_stats
Where pen_yds > tot_yds
and cfb_offensive_stats.team = cfb_penalty_stats.team
and cfb_offensive_stats.opp = cfb_penalty_stats.opp
and cfb_offensive_stats.season = cfb_penalty_stats.season
and cfb_offensive_stats.wk = cfb_penalty_stats.wk;

Select '';

##########################################
#Extra Credit: a
#Question:

#Which coach has the best total two-point convertion percentage with the highest convertions made? What is the percentage? What is the total number of convertions?
#Implement Tie-Breaking


##########################################
#Extra Credit: b
#Solution:

Select '';

Select E.coach as Coach, 
E.percentage*100 as 'Two-Point Convertion Percentage', 
E.completed as 'Total Two-Point Convertions'
From (
Select B.coach, (B.completed/B.attempted) as percentage, B.completed
From (
Select A.coach, sum(A.twoptconv_att) as attempted, sum(A.twoptconv_comp) as completed
From (
Select coach.coach, st.team, st.opp, st.season, st.wk, st.twoptconv_att, st.twoptconv_comp
From cfb_specialteams_stats as st, cfb_coach as coach
Where st.team = coach.team
and st.opp = coach.opp
and st.season = coach.season
and st.wk = coach.wk
) A
Group By A.coach
) B
Order By percentage desc, B.completed desc
Limit 1
) C, (
Select B.coach, (B.completed/B.attempted) as percentage, B.completed
From (
Select A.coach, sum(A.twoptconv_att) as attempted, sum(A.twoptconv_comp) as completed
From (
Select coach.coach, st.team, st.opp, st.season, st.wk, st.twoptconv_att, st.twoptconv_comp
From cfb_specialteams_stats as st, cfb_coach as coach
Where st.team = coach.team
and st.opp = coach.opp
and st.season = coach.season
and st.wk = coach.wk
) A
Group By A.coach
) B
Order By percentage desc, B.completed desc
) E
Where E.percentage = C.percentage
and E.completed = C.completed;
